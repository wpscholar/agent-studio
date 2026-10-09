<?php

declare(strict_types=1);

namespace AgentStudio\Application\Connections;

use AgentStudio\Application\OAuth\ManualClientNeeded;
use AgentStudio\Application\OAuth\OAuthClient;
use AgentStudio\Application\OAuth\OAuthDiscovery;
use AgentStudio\Application\OAuth\OAuthException;
use AgentStudio\Application\OAuth\Pkce;
use AgentStudio\Application\OAuth\TokenSet;
use AgentStudio\Contracts\Ability\AbilityResult;
use AgentStudio\Contracts\Clock;
use AgentStudio\Contracts\Connection\AuthRequired;
use AgentStudio\Contracts\Connection\ConnectionProblem;
use AgentStudio\Contracts\Connection\ConnectionType;
use AgentStudio\Contracts\ConnectionRepository;
use AgentStudio\Contracts\PendingSignInStore;
use AgentStudio\Contracts\SecretStore;
use AgentStudio\Domain\Connection\AuthMode;
use AgentStudio\Domain\Connection\Connection;
use AgentStudio\Domain\Connection\ConnectionStatus;
use AgentStudio\Domain\Connection\OAuthConfig;
use RuntimeException;

/**
 * Everything that happens to a connection: checking it, signing in, keeping
 * tokens fresh, and running its tools for agents.
 */
final class ConnectionService
{
    private const SIGN_IN_TTL = 900;
    private const REFRESH_MARGIN = 60;

    public function __construct(
        private readonly ConnectionRepository $connections,
        private readonly ConnectionTypes $types,
        private readonly SecretStore $secrets,
        private readonly OAuthDiscovery $discovery,
        private readonly OAuthClient $oauth,
        private readonly PendingSignInStore $pending,
        private readonly Clock $clock,
    ) {
    }

    public function type(Connection $connection): ConnectionType
    {
        $type = $this->types->get($connection->type);
        if ($type === null) {
            throw new ConnectionProblem('The plugin that provides this kind of connection is not active.');
        }

        return $type;
    }

    /**
     * Saves the API key, or removes it when $key is an empty string. Null keeps the saved one.
     */
    public function saveKey(Connection $connection, ?string $key): void
    {
        if ($key === null) {
            return;
        }

        $key === '' ? $this->secrets->delete(self::secret($connection, 'key')) : $this->secrets->set(self::secret($connection, 'key'), $key);
    }

    /**
     * Saves a client ID (and secret) the person created at the service by hand.
     */
    public function saveManualClient(Connection $connection, string $clientId, ?string $clientSecret): Connection
    {
        if ($clientSecret !== null) {
            $this->secrets->set(self::secret($connection, 'client-secret'), $clientSecret);
        }

        $settings = $connection->settings;
        $settings['oauth_client_id'] = $clientId;

        return $this->connections->save($connection->with(['settings' => $settings, 'oauth' => null]));
    }

    public function hasKey(Connection $connection): bool
    {
        return $this->secrets->get(self::secret($connection, 'key')) !== null;
    }

    public function forget(Connection $connection): void
    {
        foreach (['key', 'access', 'refresh', 'client-secret'] as $kind) {
            $this->secrets->delete(self::secret($connection, $kind));
        }
    }

    /**
     * Asks the service for its tools with the saved credentials, and records how that went.
     */
    public function check(Connection $connection): Connection
    {
        try {
            $tools = $this->type($connection)->tools($connection, $this->headers($connection));
            $connection = $connection->with([
                'status' => ConnectionStatus::Connected,
                'message' => '',
                'tools' => $tools,
            ]);
        } catch (AuthRequired $e) {
            // The screen already explains the usual case; keep only messages that add something.
            $message = $e->getMessage() === AuthRequired::DEFAULT_MESSAGE ? '' : $e->getMessage();
            $connection = $connection->with(['status' => ConnectionStatus::NeedsSignIn, 'message' => $message]);
        } catch (ConnectionProblem | OAuthException $e) {
            $connection = $connection->with(['status' => ConnectionStatus::Problem, 'message' => $e->getMessage()]);
        }

        return $this->connections->save($connection->with(['checkedAt' => $this->clock->now()]));
    }

    /**
     * Starts signing in: works out where the service signs people in, registers
     * Agent Studio there if needed, and returns the address to send the person to.
     * Returns an empty string when the service turns out not to need a sign-in.
     */
    public function beginSignIn(Connection $connection, SignInContext $context): string
    {
        $type = $this->type($connection);

        try {
            $tools = $type->tools($connection, []);
            $this->connections->save($connection->with([
                'authMode' => AuthMode::None,
                'status' => ConnectionStatus::Connected,
                'message' => '',
                'tools' => $tools,
                'checkedAt' => $this->clock->now(),
            ]));

            return '';
        } catch (AuthRequired $e) {
            $challenge = $e->wwwAuthenticate;
        }

        $resource = $this->discovery->protectedResource($type->resource($connection), $challenge);
        $issuer = $resource['authorization_servers'][0];
        $server = $this->discovery->authorizationServer($issuer);

        $config = $this->register($connection, $issuer, $server, $context);
        $config = new OAuthConfig(
            $issuer,
            (string) $server['authorization_endpoint'],
            (string) $server['token_endpoint'],
            $config['client_id'],
            $config['registration'],
            OAuthDiscovery::scopeFor($challenge, $resource['scopes_supported'], $server),
            $resource['resource'],
            0,
            ($server['authorization_response_iss_parameter_supported'] ?? false) === true
        );

        $connection = $this->connections->save($connection->with([
            'authMode' => AuthMode::OAuth,
            'oauth' => $config,
            'status' => ConnectionStatus::NeedsSignIn,
            'message' => '',
        ]));

        $state = Pkce::state();
        $verifier = Pkce::verifier();
        $this->pending->put($state, [
            'connection' => $connection->id,
            'verifier' => $verifier,
            'user' => $context->userId,
            'redirect_uri' => $context->redirectUri,
        ], self::SIGN_IN_TTL);

        return $this->oauth->authorizationUrl($config, $context->redirectUri, $state, Pkce::challenge($verifier));
    }

    /**
     * Finishes signing in when the person comes back from the service.
     */
    public function finishSignIn(string $state, string $code, ?string $issuer, int $userId): Connection
    {
        $pending = $this->pending->take($state);
        if ($pending === null || ($pending['user'] ?? null) !== $userId) {
            throw new OAuthException('This sign-in link has expired or belongs to someone else. Start signing in again.');
        }

        $connection = $this->connections->find((int) ($pending['connection'] ?? 0));
        $config = $connection?->oauth;
        if ($connection === null || $config === null) {
            throw new OAuthException('The connection was deleted while you were signing in.');
        }

        // RFC 9207: the service says who it is, so a different service cannot slip in its code.
        if (($issuer !== null && $issuer !== $config->issuer) || ($issuer === null && $config->issuerInResponse)) {
            throw new OAuthException('The sign-in came back from a different server than expected, so it was not used.');
        }

        $tokens = $this->oauth->exchangeCode(
            $config,
            $code,
            (string) ($pending['verifier'] ?? ''),
            (string) ($pending['redirect_uri'] ?? ''),
            $this->secrets->get(self::secret($connection, 'client-secret')) ?? ''
        );

        return $this->check($this->storeTokens($connection, $config, $tokens));
    }

    /**
     * Runs a tool for an agent.
     *
     * @param array<string, mixed> $input
     */
    public function call(Connection $connection, string $toolName, array $input): AbilityResult
    {
        $tool = $connection->tool($toolName);
        if ($tool === null) {
            return AbilityResult::failure(sprintf('The tool "%s" is no longer offered by %s.', $toolName, $connection->name));
        }

        try {
            $type = $this->type($connection);

            try {
                return $type->call($connection, $this->headers($connection), $tool, $input);
            } catch (AuthRequired $e) {
                // A token can stop working before it says it expires. Refresh once and retry.
                $refreshed = $this->refreshed($connection);
                if ($refreshed === null) {
                    throw $e;
                }

                return $type->call($refreshed, $this->headers($refreshed), $tool, $input);
            }
        } catch (AuthRequired | OAuthException $e) {
            $this->connections->save($connection->with(['status' => ConnectionStatus::NeedsSignIn, 'message' => $e->getMessage()]));

            return AbilityResult::failure(sprintf('The connection to %s needs an administrator to sign in again.', $connection->name));
        } catch (ConnectionProblem $e) {
            return AbilityResult::failure($e->getMessage());
        }
    }

    /**
     * The credentials to send, refreshing an OAuth token that is about to expire.
     *
     * @return array<string, string>
     */
    public function headers(Connection $connection): array
    {
        if ($connection->authMode === AuthMode::Key) {
            $key = $this->secrets->get(self::secret($connection, 'key'));
            if ($key === null) {
                throw new AuthRequired('', 'No API key is saved for this connection.');
            }

            return strcasecmp($connection->keyHeader, 'Authorization') === 0
                ? ['Authorization' => 'Bearer ' . $key]
                : [$connection->keyHeader => $key];
        }

        if ($connection->authMode === AuthMode::OAuth) {
            $config = $connection->oauth;
            if ($config !== null && $config->expiresAt > 0 && $config->expiresAt <= $this->clock->now() + self::REFRESH_MARGIN) {
                $connection = $this->refreshed($connection) ?? $connection;
            }

            $token = $this->secrets->get(self::secret($connection, 'access'));
            if ($token === null) {
                throw new AuthRequired('', 'Nobody has signed in to this service yet.');
            }

            return ['Authorization' => 'Bearer ' . $token];
        }

        return [];
    }

    /**
     * Picks how Agent Studio identifies itself to the sign-in server: a client ID
     * the person entered, the site's own metadata URL, or a fresh registration.
     *
     * @param array<string, mixed> $server
     *
     * @return array{client_id: string, registration: string}
     */
    private function register(Connection $connection, string $issuer, array $server, SignInContext $context): array
    {
        $manual = $connection->setting('oauth_client_id');
        if ($manual !== '') {
            return ['client_id' => $manual, 'registration' => OAuthConfig::REGISTERED_MANUALLY];
        }

        $previous = $connection->oauth;
        if ($previous !== null && $previous->issuer === $issuer && $previous->registration !== OAuthConfig::REGISTERED_MANUALLY) {
            return ['client_id' => $previous->clientId, 'registration' => $previous->registration];
        }

        if (($server['client_id_metadata_document_supported'] ?? false) === true && $context->clientMetadataUrl !== '') {
            return ['client_id' => $context->clientMetadataUrl, 'registration' => OAuthConfig::REGISTERED_BY_URL];
        }

        $endpoint = $server['registration_endpoint'] ?? null;
        if (is_string($endpoint) && OAuthDiscovery::isHttpUrl($endpoint)) {
            $client = $this->oauth->register($endpoint, $context->redirectUri, $context->clientName, $context->clientUri);
            if ($client['client_secret'] !== '') {
                $this->secrets->set(self::secret($connection, 'client-secret'), $client['client_secret']);
            }

            return ['client_id' => $client['client_id'], 'registration' => OAuthConfig::REGISTERED_DYNAMICALLY];
        }

        $docs = $server['service_documentation'] ?? '';

        throw new ManualClientNeeded($issuer, is_string($docs) && OAuthDiscovery::isHttpUrl($docs) ? $docs : '');
    }

    private function refreshed(Connection $connection): ?Connection
    {
        $config = $connection->oauth;
        $refresh = $this->secrets->get(self::secret($connection, 'refresh'));
        if ($connection->authMode !== AuthMode::OAuth || $config === null || $refresh === null) {
            return null;
        }

        $tokens = $this->oauth->refresh($config, $refresh, $this->secrets->get(self::secret($connection, 'client-secret')) ?? '');

        return $this->storeTokens($connection, $config, $tokens);
    }

    private function storeTokens(Connection $connection, OAuthConfig $config, TokenSet $tokens): Connection
    {
        $this->secrets->set(self::secret($connection, 'access'), $tokens->accessToken);
        if ($tokens->refreshToken !== '') {
            $this->secrets->set(self::secret($connection, 'refresh'), $tokens->refreshToken);
        }

        return $this->connections->save($connection->with([
            'authMode' => AuthMode::OAuth,
            'oauth' => $config->withExpiry($tokens->expiresIn > 0 ? $this->clock->now() + $tokens->expiresIn : 0),
        ]));
    }

    private static function secret(Connection $connection, string $kind): string
    {
        if ($connection->id <= 0) {
            throw new RuntimeException('Save the connection before storing its secrets.');
        }

        return 'connection-' . $connection->id . '-' . $kind;
    }
}
