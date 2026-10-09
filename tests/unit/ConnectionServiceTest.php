<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Application\Connections\ConnectionAbilityGateway;
use AgentStudio\Application\Connections\ConnectionService;
use AgentStudio\Application\Connections\ConnectionTypes;
use AgentStudio\Application\Connections\SignInContext;
use AgentStudio\Application\Mcp\McpClient;
use AgentStudio\Application\OAuth\ManualClientNeeded;
use AgentStudio\Application\OAuth\OAuthClient;
use AgentStudio\Application\OAuth\OAuthDiscovery;
use AgentStudio\Application\OAuth\OAuthException;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Contracts\Http\HttpResponse;
use AgentStudio\Domain\Ability\Risk;
use AgentStudio\Domain\Connection\AuthMode;
use AgentStudio\Domain\Connection\Connection;
use AgentStudio\Domain\Connection\ConnectionStatus;
use AgentStudio\Tests\Support\FakeHttpClient;
use AgentStudio\Tests\Support\FakeMcpServer;
use AgentStudio\Tests\Support\FixedClock;
use AgentStudio\Tests\Support\InMemoryConnectionRepository;
use AgentStudio\Tests\Support\InMemoryPendingSignInStore;
use AgentStudio\Tests\Support\InMemorySecretStore;
use AgentStudio\Tests\Support\McpTestConnectionType;

final class ConnectionServiceTest extends TestCase
{
    private const MCP = 'https://mcp.example.com/mcp';
    private const AUTH = 'https://auth.example.com';
    private const REDIRECT = 'https://site.example/wp-admin/admin-post.php?action=agent_studio_oauth_callback';

    private FakeMcpServer $server;
    private FakeHttpClient $http;
    private InMemoryConnectionRepository $connections;
    private InMemorySecretStore $secrets;
    private InMemoryPendingSignInStore $pending;
    private FixedClock $clock;
    private ConnectionService $service;

    /** @var array<string, mixed> */
    private array $authServer = [];

    /** @var list<array<string, string>> */
    private array $tokenRequests = [];

    protected function setUp(): void
    {
        $this->server = new FakeMcpServer(token: 'access-1');
        $this->authServer = [
            'issuer' => self::AUTH,
            'authorization_endpoint' => self::AUTH . '/authorize',
            'token_endpoint' => self::AUTH . '/token',
            'registration_endpoint' => self::AUTH . '/register',
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ['read', 'offline_access'],
        ];

        $this->http = new FakeHttpClient(fn (HttpRequest $request): ?HttpResponse => $this->route($request));
        $this->connections = new InMemoryConnectionRepository();
        $this->secrets = new InMemorySecretStore();
        $this->pending = new InMemoryPendingSignInStore();
        $this->clock = new FixedClock();

        $types = new ConnectionTypes();
        $types->register(new McpTestConnectionType(new McpClient($this->http)));

        $this->service = new ConnectionService(
            $this->connections,
            $types,
            $this->secrets,
            new OAuthDiscovery($this->http),
            new OAuthClient($this->http),
            $this->pending,
            $this->clock
        );
    }

    private function route(HttpRequest $request): ?HttpResponse
    {
        $url = $request->url;
        if (str_starts_with($url, self::MCP)) {
            return $this->server->handle($request);
        }
        if ($url === 'https://mcp.example.com/.well-known/oauth-protected-resource/mcp') {
            return FakeHttpClient::json(['resource' => self::MCP, 'authorization_servers' => [self::AUTH], 'scopes_supported' => ['read']]);
        }
        if ($url === self::AUTH . '/.well-known/oauth-authorization-server') {
            return FakeHttpClient::json($this->authServer);
        }
        if ($url === self::AUTH . '/register') {
            return FakeHttpClient::json(['client_id' => 'dyn-123'], 201);
        }
        if ($url === self::AUTH . '/token') {
            parse_str($request->body, $fields);
            /** @var array<string, string> $fields */
            $this->tokenRequests[] = $fields;
            $ok = ($fields['grant_type'] === 'authorization_code' && $fields['code'] === 'good')
                || ($fields['grant_type'] === 'refresh_token' && str_starts_with($fields['refresh_token'], 'refresh-'));

            return $ok
                ? FakeHttpClient::json(['access_token' => 'access-1', 'token_type' => 'Bearer', 'expires_in' => 3600, 'refresh_token' => 'refresh-' . count($this->tokenRequests)])
                : FakeHttpClient::json(['error' => 'invalid_grant', 'error_description' => 'Code expired.'], 400);
        }

        return new HttpResponse(404, [], '');
    }

    private function connection(): Connection
    {
        return $this->connections->save(new Connection(0, 'mcp', 'Issues', 'issues', ['url' => self::MCP]));
    }

    private function context(string $metadataUrl = '', int $user = 7): SignInContext
    {
        return new SignInContext($user, self::REDIRECT, $metadataUrl, 'Agent Studio on Test', 'https://site.example/');
    }

    /**
     * @return array<string, string>
     */
    private static function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    public function testAnOpenServerConnectsWithoutCredentials(): void
    {
        $this->server->token = null;

        $connection = $this->service->check($this->connection());

        $this->assertSame(ConnectionStatus::Connected, $connection->status);
        $this->assertCount(3, $connection->tools);
        $this->assertSame($this->clock->now, $connection->checkedAt);
    }

    public function testAProtectedServerAsksForSignIn(): void
    {
        $connection = $this->service->check($this->connection());

        $this->assertSame(ConnectionStatus::NeedsSignIn, $connection->status);
        $this->assertSame([], $connection->tools);
    }

    public function testAnApiKeyGoesInTheHeaderTheServiceAsksFor(): void
    {
        $connection = $this->connection()->with(['authMode' => AuthMode::Key]);
        $this->service->saveKey($connection, 'access-1');

        $this->assertSame(ConnectionStatus::Connected, $this->service->check($connection)->status);
        $this->assertSame(['X-Api-Key' => 'k'], (function () use ($connection): array {
            $custom = $connection->with(['keyHeader' => 'X-Api-Key']);
            $this->service->saveKey($custom, 'k');

            return $this->service->headers($custom);
        })());
    }

    public function testSigningInRegistersDiscoversAndUsesPkce(): void
    {
        $url = $this->service->beginSignIn($this->connection(), $this->context());

        $query = self::query($url);
        $this->assertStringStartsWith(self::AUTH . '/authorize?', $url);
        $this->assertSame('dyn-123', $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(self::MCP, $query['resource']);
        $this->assertSame('read offline_access', $query['scope']);
        $this->assertSame(self::REDIRECT, $query['redirect_uri']);
        $this->assertSame([self::REDIRECT], FakeHttpClient::body($this->http->to(self::AUTH . '/register')[0])['redirect_uris']);

        $pending = $this->pending->items[$query['state']];
        $this->assertSame($query['code_challenge'], rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '='));
    }

    public function testFinishingSignInStoresTokensAndConnects(): void
    {
        $connection = $this->connection();
        $state = self::query($this->service->beginSignIn($connection, $this->context()))['state'];

        $connection = $this->service->finishSignIn($state, 'good', self::AUTH, 7);

        $this->assertSame(ConnectionStatus::Connected, $connection->status);
        $this->assertSame('access-1', $this->secrets->get('connection-1-access'));
        $this->assertSame('refresh-1', $this->secrets->get('connection-1-refresh'));
        $this->assertSame($this->clock->now + 3600, $connection->oauth?->expiresAt);
        $this->assertSame(self::MCP, $this->tokenRequests[0]['resource']);
        $this->assertSame('dyn-123', $this->tokenRequests[0]['client_id']);
    }

    public function testASignInLinkOnlyWorksOnceAndForThePersonWhoStartedIt(): void
    {
        $state = self::query($this->service->beginSignIn($this->connection(), $this->context()))['state'];

        try {
            $this->service->finishSignIn($state, 'good', self::AUTH, 99);
            $this->fail('Another user must not finish the sign-in.');
        } catch (OAuthException) {
        }

        $this->expectException(OAuthException::class);
        $this->service->finishSignIn($state, 'good', self::AUTH, 7);
    }

    public function testACodeFromADifferentServerIsRefused(): void
    {
        $state = self::query($this->service->beginSignIn($this->connection(), $this->context()))['state'];

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('different server');

        $this->service->finishSignIn($state, 'good', 'https://evil.example', 7);
    }

    public function testTheSiteUrlIsUsedAsClientIdWhenTheServerAcceptsIt(): void
    {
        $this->authServer['client_id_metadata_document_supported'] = true;

        $url = $this->service->beginSignIn($this->connection(), $this->context('https://site.example/wp-json/agent-studio/v1/oauth/client'));

        $this->assertSame('https://site.example/wp-json/agent-studio/v1/oauth/client', self::query($url)['client_id']);
        $this->assertSame([], $this->http->to(self::AUTH . '/register'));
    }

    public function testAServiceWithoutSelfRegistrationNeedsAClientIdFromThePerson(): void
    {
        unset($this->authServer['registration_endpoint']);
        $connection = $this->connection();

        try {
            $this->service->beginSignIn($connection, $this->context());
            $this->fail('Expected ManualClientNeeded.');
        } catch (ManualClientNeeded) {
        }

        $connection = $this->service->saveManualClient($this->connections->find($connection->id) ?? $connection, 'made-by-hand', 'shh');
        $url = $this->service->beginSignIn($connection, $this->context());

        $this->assertSame('made-by-hand', self::query($url)['client_id']);
        $state = self::query($url)['state'];
        $this->service->finishSignIn($state, 'good', self::AUTH, 7);
        $this->assertArrayNotHasKey('client_id', $this->tokenRequests[0], 'A client with a secret authenticates with it instead.');
    }

    public function testRefusesASignInServerThatDoesNotSupportPkce(): void
    {
        $this->authServer['code_challenge_methods_supported'] = ['plain'];

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('PKCE');

        $this->service->beginSignIn($this->connection(), $this->context());
    }

    public function testRefusesSignInDetailsThatDoNotMatchTheirAddress(): void
    {
        $this->authServer['issuer'] = 'https://other.example.com';

        $this->expectException(OAuthException::class);

        $this->service->beginSignIn($this->connection(), $this->context());
    }

    public function testATokenAboutToExpireIsRefreshedFirst(): void
    {
        $state = self::query($this->service->beginSignIn($this->connection(), $this->context()))['state'];
        $this->service->finishSignIn($state, 'good', self::AUTH, 7);
        $this->clock->advance(3590);

        $headers = $this->service->headers($this->connections->find(1) ?? $this->connection());

        $this->assertSame(['Authorization' => 'Bearer access-1'], $headers);
        $this->assertSame('refresh_token', $this->tokenRequests[1]['grant_type']);
        $this->assertSame('refresh-2', $this->secrets->get('connection-1-refresh'), 'A rotated refresh token replaces the old one.');
    }

    public function testAgentsSeeConnectionToolsAndCanRunThem(): void
    {
        $this->server->token = null;
        $connection = $this->service->check($this->connection());
        $gateway = new ConnectionAbilityGateway($this->connections, $this->service);

        $infos = $gateway->available();

        $this->assertSame('mcp-issues/search_issues', $infos[0]->name);
        $this->assertSame('Issues', $infos[0]->category);
        $this->assertSame(Risk::Read, $infos[0]->risk);
        $this->assertStringContainsString('connected service "Issues"', $infos[0]->description);

        $ok = $gateway->execute('mcp-issues/create_issue', ['title' => 'Bug']);
        $this->assertTrue($ok->ok);
        $this->assertSame([['name' => 'create_issue', 'arguments' => ['title' => 'Bug'], '_meta' => $this->server->calls[0]['_meta']]], $this->server->calls);

        $failed = $gateway->execute('mcp-issues/delete_repo', []);
        $this->assertFalse($failed->ok);
        $this->assertSame('Not allowed.', $failed->error);

        $this->assertFalse($gateway->execute('mcp-gone/anything', [])->ok);
        $this->assertSame($connection->id, $this->connections->findBySlug('issues')?->id);
    }

    public function testATokenTheServiceStopsAcceptingMarksTheConnectionForSignIn(): void
    {
        $this->server->token = null;
        $connection = $this->service->check($this->connection())->with(['authMode' => AuthMode::Key]);
        $this->service->saveKey($connection, 'old');
        $this->connections->save($connection);
        $this->server->token = 'new';

        $result = $this->service->call($connection, 'create_issue', []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('sign in again', $result->error);
        $this->assertSame(ConnectionStatus::NeedsSignIn, $this->connections->find($connection->id)?->status);
    }

    public function testParsesChallenges(): void
    {
        $this->assertSame(
            ['realm' => 'mcp', 'resource_metadata' => 'https://x/.well-known/a', 'scope' => 'a b', 'error' => 'insufficient_scope'],
            OAuthDiscovery::challenge('Bearer realm="mcp", resource_metadata="https://x/.well-known/a", scope="a b", error=insufficient_scope')
        );
        $this->assertSame('https://mcp.example.com/mcp', OAuthDiscovery::canonicalResource('HTTPS://MCP.Example.com/mcp/#frag'));
    }
}
