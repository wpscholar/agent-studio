<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Rest;

use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use AgentStudio\Application\Connections\ConnectionAbilityGateway;
use AgentStudio\Application\Connections\ConnectionService;
use AgentStudio\Application\Connections\ConnectionTypes;
use AgentStudio\Application\Connections\SignInContext;
use AgentStudio\Application\OAuth\ManualClientNeeded;
use AgentStudio\Application\OAuth\OAuthException;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\Connection\ConnectionProblem;
use AgentStudio\Contracts\Connection\ConnectionType;
use AgentStudio\Contracts\ConnectionRepository;
use AgentStudio\Domain\Connection\AuthMode;
use AgentStudio\Domain\Connection\Connection;
use AgentStudio\Domain\Connection\ConnectionStatus;
use AgentStudio\Domain\Connection\RemoteTool;
use AgentStudio\Domain\Setup\SetupField;
use AgentStudio\Domain\Skill\SkillMarkdown;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Urls;

/**
 * The Connections screen's API.
 *
 *   GET    /connection-types               Kinds of connection on this site.
 *   GET    /connections                    List.
 *   POST   /connections                    Add one and check it. Body: type, name, settings.
 *   GET    /connections/{id}               One, with its tools.
 *   PUT    /connections/{id}               Rename or change settings.
 *   DELETE /connections/{id}               Remove it and its saved credentials.
 *   POST   /connections/{id}/check         Ask the service for its tools again.
 *   POST   /connections/{id}/key           Use an API key. Body: key, header.
 *   POST   /connections/{id}/sign-in       Start signing in. Returns the address to open.
 *   POST   /connections/{id}/client        Save a client ID made by hand at the service.
 *   GET    /oauth/client                   This site's OAuth client metadata document (public).
 */
final class ConnectionsController
{
    public const CALLBACK_ACTION = 'agent_studio_oauth_callback';

    public function __construct(
        private readonly ConnectionRepository $connections,
        private readonly ConnectionService $service,
        private readonly ConnectionTypes $types,
        private readonly AgentRepository $agents,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_post_' . self::CALLBACK_ACTION, [$this, 'callback']);
    }

    public function registerRoutes(): void
    {
        $can = [Capabilities::class, 'canManage'];
        $ns = 'agent-studio/v1';
        $id = '/connections/(?P<id>\d+)';

        register_rest_route($ns, '/connection-types', [['methods' => 'GET', 'callback' => [$this, 'types'], 'permission_callback' => $can]]);
        register_rest_route($ns, '/connections', [
            ['methods' => 'GET', 'callback' => [$this, 'index'], 'permission_callback' => $can],
            ['methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => $can],
        ]);
        register_rest_route($ns, $id, [
            ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $can],
            ['methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => $can],
            ['methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => $can],
        ]);
        register_rest_route($ns, $id . '/check', [['methods' => 'POST', 'callback' => [$this, 'check'], 'permission_callback' => $can]]);
        register_rest_route($ns, $id . '/key', [['methods' => 'POST', 'callback' => [$this, 'key'], 'permission_callback' => $can]]);
        register_rest_route($ns, $id . '/sign-in', [['methods' => 'POST', 'callback' => [$this, 'signIn'], 'permission_callback' => $can]]);
        register_rest_route($ns, $id . '/client', [['methods' => 'POST', 'callback' => [$this, 'client'], 'permission_callback' => $can]]);
        register_rest_route($ns, '/oauth/client', [['methods' => 'GET', 'callback' => [$this, 'clientMetadata'], 'permission_callback' => '__return_true']]);
    }

    public function types(): WP_REST_Response
    {
        return new WP_REST_Response(array_map(
            static fn (ConnectionType $type): array => [
                'key' => $type->key(),
                'label' => $type->label(),
                'description' => $type->description(),
                'fields' => array_map(static fn (SetupField $field): array => $field->toArray(), $type->fields()),
            ],
            $this->types->all()
        ));
    }

    public function index(): WP_REST_Response
    {
        $usage = $this->usage();

        return new WP_REST_Response(array_map(
            fn (Connection $connection): array => $this->summary($connection, $usage),
            $this->connections->all()
        ));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function show(WP_REST_Request $request)
    {
        $connection = $this->find($request);

        return $connection instanceof WP_Error ? $connection : new WP_REST_Response($this->detail($connection));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        $type = $this->types->get((string) $request->get_param('type'));
        if ($type === null) {
            return self::error('agent_studio_connection_type', __('That kind of connection is not available.', 'agent-studio'));
        }

        $name = trim(sanitize_text_field((string) $request->get_param('name')));
        $settings = $this->settings($type, $request->get_param('settings'), []);
        $problem = $name === '' ? __('Give the connection a name, like the service it connects to.', 'agent-studio') : $type->validate($settings);
        if ($problem !== null) {
            return self::error('agent_studio_connection_invalid', $problem);
        }

        try {
            $connection = $this->connections->save(new Connection(0, $type->key(), $name, $this->uniqueSlug($name), $settings));
        } catch (RuntimeException $e) {
            return self::error('agent_studio_connection_not_saved', $e->getMessage(), 500);
        }

        return new WP_REST_Response($this->detail($this->service->check($connection)), 201);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        $connection = $this->find($request);
        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $type = $this->service->type($connection);
        $name = trim(sanitize_text_field((string) ($request->get_param('name') ?? $connection->name)));
        $settings = $this->settings($type, $request->get_param('settings') ?? $connection->settings, $connection->settings);
        $problem = $name === '' ? __('Give the connection a name.', 'agent-studio') : $type->validate($settings);
        if ($problem !== null) {
            return self::error('agent_studio_connection_invalid', $problem);
        }

        $moved = $type->resource($connection) !== $type->resource($connection->with(['settings' => $settings]));
        $connection = $this->connections->save($connection->with(['name' => $name, 'settings' => $settings]));

        // A new address is a different service: check it, and do not reuse its sign-in.
        if ($moved) {
            $this->service->forget($connection);
            $connection = $this->service->check($this->connections->save($connection->with(['authMode' => AuthMode::None, 'oauth' => null])));
        }

        return new WP_REST_Response($this->detail($connection));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function destroy(WP_REST_Request $request)
    {
        $connection = $this->find($request);
        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $this->service->forget($connection);
        $this->connections->delete($connection->id);

        return new WP_REST_Response(['deleted' => true]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function check(WP_REST_Request $request)
    {
        $connection = $this->find($request);

        return $connection instanceof WP_Error ? $connection : new WP_REST_Response($this->detail($this->service->check($connection)));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function key(WP_REST_Request $request)
    {
        $connection = $this->find($request);
        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $key = trim((string) $request->get_param('key'));
        $header = trim((string) ($request->get_param('header') ?: 'Authorization'));
        if (preg_match('/^[A-Za-z0-9-]{1,64}$/', $header) !== 1) {
            return self::error('agent_studio_connection_invalid', __('The header name can only use letters, numbers and hyphens.', 'agent-studio'));
        }
        if ($key === '' && ! $this->service->hasKey($connection)) {
            return self::error('agent_studio_connection_invalid', __('Paste the API key or token.', 'agent-studio'));
        }
        if (preg_match('/[\r\n]/', $key) === 1) {
            return self::error('agent_studio_connection_invalid', __('The key cannot contain line breaks.', 'agent-studio'));
        }

        $connection = $this->connections->save($connection->with(['authMode' => AuthMode::Key, 'keyHeader' => $header]));
        $this->service->saveKey($connection, $key === '' ? null : $key);

        return new WP_REST_Response($this->detail($this->service->check($connection)));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function signIn(WP_REST_Request $request)
    {
        $connection = $this->find($request);
        if ($connection instanceof WP_Error) {
            return $connection;
        }

        try {
            $url = $this->service->beginSignIn($connection, $this->context());
        } catch (ManualClientNeeded $e) {
            return new WP_Error('agent_studio_manual_client', $e->getMessage(), [
                'status' => 409,
                'redirect_uri' => self::redirectUri(),
                'server' => (string) wp_parse_url($e->issuer, PHP_URL_HOST),
                'docs' => $e->documentation,
            ]);
        } catch (OAuthException | ConnectionProblem $e) {
            return self::error('agent_studio_sign_in_failed', $e->getMessage(), 502);
        }

        $fresh = $this->connections->find($connection->id) ?? $connection;

        return new WP_REST_Response(['url' => $url, 'connection' => $this->detail($fresh)]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function client(WP_REST_Request $request)
    {
        $connection = $this->find($request);
        if ($connection instanceof WP_Error) {
            return $connection;
        }

        $clientId = trim(sanitize_text_field((string) $request->get_param('client_id')));
        if ($clientId === '') {
            return self::error('agent_studio_connection_invalid', __('Paste the client ID.', 'agent-studio'));
        }

        $secret = trim((string) $request->get_param('client_secret'));
        $this->service->saveManualClient($connection, $clientId, $secret === '' ? null : $secret);

        return $this->signIn($request);
    }

    /**
     * Where the person lands after signing in at the service.
     */
    public function callback(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to do that.', 'agent-studio'), '', ['response' => 403]);
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- The OAuth state parameter is the CSRF check here.
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $issuer = isset($_GET['iss']) ? esc_url_raw(wp_unslash($_GET['iss'])) : null;
        $error = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : (isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '');
        // phpcs:enable

        if ($error !== '' || $code === '' || $state === '') {
            $this->callbackDone(0, 'error', $error !== '' ? sprintf(
                /* translators: %s: error from the service */
                __('Signing in did not finish: %s', 'agent-studio'),
                $error
            ) : __('Signing in did not finish.', 'agent-studio'));
        }

        try {
            $connection = $this->service->finishSignIn($state, $code, $issuer === '' ? null : $issuer, get_current_user_id());
        } catch (OAuthException | ConnectionProblem $e) {
            $this->callbackDone(0, 'error', $e->getMessage());
        }

        $this->callbackDone(
            $connection->id,
            $connection->status === ConnectionStatus::Connected ? 'success' : 'error',
            $connection->status === ConnectionStatus::Connected
                ? sprintf(
                    /* translators: %s: connection name */
                    __('Signed in. %s is connected.', 'agent-studio'),
                    $connection->name
                )
                : $connection->message
        );
    }

    /**
     * The client ID metadata document: how this site describes itself to sign-in servers
     * that accept a URL as the client ID, so no registration is needed.
     */
    public function clientMetadata(): WP_REST_Response
    {
        return new WP_REST_Response([
            'client_id' => self::clientMetadataUrl(),
            'client_name' => self::clientName(),
            'client_uri' => home_url('/'),
            'redirect_uris' => [self::redirectUri()],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);
    }

    public static function redirectUri(): string
    {
        return admin_url('admin-post.php?action=' . self::CALLBACK_ACTION);
    }

    private static function clientMetadataUrl(): string
    {
        return rest_url('agent-studio/v1/oauth/client');
    }

    private static function clientName(): string
    {
        return sprintf(
            /* translators: %s: site name */
            __('Agent Studio on %s', 'agent-studio'),
            wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES)
        );
    }

    private function context(): SignInContext
    {
        $metadataUrl = self::clientMetadataUrl();

        return new SignInContext(
            get_current_user_id(),
            self::redirectUri(),
            str_starts_with($metadataUrl, 'https://') ? $metadataUrl : '',
            self::clientName(),
            home_url('/')
        );
    }

    private function callbackDone(int $connectionId, string $type, string $message): never
    {
        \AgentStudio\WordPress\Admin\Flash::redirect(
            $connectionId > 0 ? Urls::connection($connectionId) : Urls::connections(),
            $type,
            $message
        );
    }

    /**
     * @return Connection|WP_Error
     */
    private function find(WP_REST_Request $request)
    {
        $connection = $this->connections->find((int) $request->get_param('id'));

        return $connection ?? self::error('agent_studio_connection_not_found', __('That connection no longer exists.', 'agent-studio'), 404);
    }

    /**
     * @param array<string, string> $current
     *
     * @return array<string, string>
     */
    private function settings(ConnectionType $type, mixed $input, array $current): array
    {
        $input = is_array($input) ? $input : [];
        $settings = $current;
        foreach ($type->fields() as $field) {
            if (! $field->isSecret() && array_key_exists($field->key, $input)) {
                $settings[$field->key] = $field->clean($input[$field->key]);
            }
        }

        return $settings;
    }

    private function uniqueSlug(string $name): string
    {
        $base = substr(SkillMarkdown::slugify($name), 0, 32);
        $base = $base === '' ? 'connection' : trim($base, '-');
        $slug = $base;
        for ($i = 2; $this->connections->findBySlug($slug) !== null; $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    /**
     * Agents that may use each connection's tools, by connection slug.
     *
     * @return array<string, list<array{id: int, name: string, url: string}>>
     */
    private function usage(): array
    {
        $usage = [];
        foreach ($this->agents->all() as $agent) {
            $seen = [];
            foreach ($agent->abilities as $ability) {
                if (! str_starts_with($ability, ConnectionAbilityGateway::PREFIX)) {
                    continue;
                }
                $slug = substr(explode('/', $ability, 2)[0], strlen(ConnectionAbilityGateway::PREFIX));
                if (! isset($seen[$slug])) {
                    $seen[$slug] = true;
                    $usage[$slug][] = ['id' => $agent->id, 'name' => $agent->name, 'url' => Urls::editAgent($agent->id)];
                }
            }
        }

        return $usage;
    }

    /**
     * @param array<string, list<array{id: int, name: string, url: string}>> $usage
     *
     * @return array<string, mixed>
     */
    private function summary(Connection $connection, array $usage): array
    {
        $type = $this->types->get($connection->type);

        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'slug' => $connection->slug,
            'type' => $connection->type,
            'typeLabel' => $type?->label() ?? $connection->type,
            'address' => $connection->setting('url'),
            'status' => $connection->status->value,
            'message' => $connection->message,
            'toolCount' => count($connection->tools),
            'checkedAt' => $connection->checkedAt,
            'usedBy' => $usage[$connection->slug] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Connection $connection): array
    {
        $type = $this->types->get($connection->type);

        return $this->summary($connection, $this->usage()) + [
            'settings' => $connection->settings,
            'fields' => $type === null ? [] : array_map(static fn (SetupField $field): array => $field->toArray(), $type->fields()),
            'authMode' => $connection->authMode->value,
            'keyHeader' => $connection->keyHeader,
            'hasKey' => $this->service->hasKey($connection),
            'signIn' => $connection->oauth === null ? null : [
                'server' => (string) wp_parse_url($connection->oauth->issuer, PHP_URL_HOST),
                'manualClient' => $connection->setting('oauth_client_id') !== '',
                'expiresAt' => $connection->oauth->expiresAt,
            ],
            'redirectUri' => self::redirectUri(),
            'tools' => array_map(
                static fn (RemoteTool $tool): array => [
                    'name' => $tool->name,
                    'ability' => ConnectionAbilityGateway::abilityName($connection, $tool),
                    'title' => $tool->title !== '' ? $tool->title : $tool->name,
                    'description' => $tool->description,
                    'risk' => $tool->risk()->value,
                ],
                $connection->tools
            ),
        ];
    }

    private static function error(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}
