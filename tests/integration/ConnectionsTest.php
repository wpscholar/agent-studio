<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Contracts\Http\HttpResponse;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Infrastructure\Secrets\EncryptedOptionSecretStore;
use AgentStudio\Tests\Support\FakeHttpClient;
use AgentStudio\Tests\Support\FakeMcpServer;
use AgentStudio\WordPress\Plugin;
use AgentStudio\WordPress\Rest\ConnectionsController;

/**
 * Connections against a fake MCP server and sign-in server, answered through
 * the WordPress HTTP API so the real client code runs end to end.
 */
final class ConnectionsTest extends IntegrationTestCase
{
    private const MCP = 'https://mcp.example.com/mcp';
    private const AUTH = 'https://auth.example.com';

    private FakeMcpServer $server;

    public function set_up(): void
    {
        parent::set_up();

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        $this->server = new FakeMcpServer();
        add_filter('pre_http_request', [$this, 'answer'], 10, 3);
    }

    public function tear_down(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        remove_filter('pre_http_request', [$this, 'answer'], 10);

        parent::tear_down();
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function answer(mixed $pre, array $args, string $url): array
    {
        $request = new HttpRequest((string) $args['method'], $url, (array) $args['headers'], (string) ($args['body'] ?? ''));
        $response = $this->route($request);

        return [
            'headers' => $response->headers,
            'body' => $response->body,
            'response' => ['code' => $response->status, 'message' => ''],
            'cookies' => [],
            'filename' => null,
        ];
    }

    private function route(HttpRequest $request): HttpResponse
    {
        return match (true) {
            str_starts_with($request->url, self::MCP) => $this->server->handle($request) ?? new HttpResponse(500, [], ''),
            $request->url === 'https://mcp.example.com/.well-known/oauth-protected-resource/mcp' => FakeHttpClient::json(['resource' => self::MCP, 'authorization_servers' => [self::AUTH]]),
            $request->url === self::AUTH . '/.well-known/oauth-authorization-server' => FakeHttpClient::json([
                'issuer' => self::AUTH,
                'authorization_endpoint' => self::AUTH . '/authorize',
                'token_endpoint' => self::AUTH . '/token',
                'registration_endpoint' => self::AUTH . '/register',
                'code_challenge_methods_supported' => ['S256'],
            ]),
            $request->url === self::AUTH . '/register' => FakeHttpClient::json(['client_id' => 'dyn-1'], 201),
            $request->url === self::AUTH . '/token' => FakeHttpClient::json(['access_token' => 'tok', 'token_type' => 'bearer', 'expires_in' => 3600]),
            default => new HttpResponse(404, [], ''),
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    private function call(string $method, string $path, array $body = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, '/agent-studio/v1/' . $path);
        foreach ($body as $key => $value) {
            $request->set_param($key, $value);
        }

        return rest_do_request($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function add(string $name = 'Issues'): array
    {
        $response = $this->call('POST', 'connections', ['type' => 'mcp', 'name' => $name, 'settings' => ['url' => self::MCP]]);
        $this->assertSame(201, $response->get_status(), (string) wp_json_encode($response->get_data()));

        return $response->get_data();
    }

    public function test_the_mcp_type_is_offered_with_plain_setup_fields(): void
    {
        $types = $this->call('GET', 'connection-types')->get_data();

        $this->assertSame('mcp', $types[0]['key']);
        $this->assertSame('url', $types[0]['fields'][0]['key']);
        $this->assertNotSame('', $types[0]['fields'][0]['help']);
    }

    public function test_adding_an_open_server_lists_its_tools(): void
    {
        $connection = $this->add();

        $this->assertSame('connected', $connection['status']);
        $this->assertSame('issues', $connection['slug']);
        $this->assertSame(['mcp-issues/search_issues', 'mcp-issues/create_issue', 'mcp-issues/delete_repo'], array_column($connection['tools'], 'ability'));
        $this->assertSame(['read', 'external', 'destructive'], array_column($connection['tools'], 'risk'));
        $this->assertSame('issues-2', $this->add()['slug'], 'Two connections never share tool names.');
    }

    public function test_bad_addresses_are_explained(): void
    {
        $response = $this->call('POST', 'connections', ['type' => 'mcp', 'name' => 'X', 'settings' => ['url' => 'ftp://example.com']]);

        $this->assertSame(400, $response->get_status());
        $this->assertStringContainsString('https://', $response->get_data()['message']);
    }

    public function test_an_api_key_connects_and_is_never_sent_back(): void
    {
        $this->server->token = 'sk-live-secret';
        $connection = $this->add();
        $this->assertSame('needs_sign_in', $connection['status']);

        $response = $this->call('POST', 'connections/' . $connection['id'] . '/key', ['key' => 'sk-live-secret']);

        $this->assertSame('connected', $response->get_data()['status']);
        $this->assertTrue($response->get_data()['hasKey']);
        $this->assertStringNotContainsString('sk-live-secret', (string) wp_json_encode($this->call('GET', 'connections/' . $connection['id'])->get_data()));
        $this->assertStringNotContainsString('sk-live-secret', (string) wp_json_encode(get_post_meta($connection['id'])));
    }

    public function test_signing_in_end_to_end(): void
    {
        $this->server->token = 'tok';
        $connection = $this->add();

        $url = $this->call('POST', 'connections/' . $connection['id'] . '/sign-in')->get_data()['url'];
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('dyn-1', $query['client_id']);
        $this->assertSame(ConnectionsController::redirectUri(), $query['redirect_uri']);

        $_GET = ['state' => $query['state'], 'code' => 'abc', 'iss' => self::AUTH];
        $location = $this->redirectOf(static fn () => Plugin::container()->get(ConnectionsController::class)->callback());

        $this->assertStringContainsString('connection=' . $connection['id'], $location);
        $this->assertSame('connected', $this->call('GET', 'connections/' . $connection['id'])->get_data()['status']);
    }

    public function test_the_client_metadata_document_is_public_and_names_the_callback(): void
    {
        wp_set_current_user(0);

        $document = $this->call('GET', 'oauth/client')->get_data();

        $this->assertSame(rest_url('agent-studio/v1/oauth/client'), $document['client_id']);
        $this->assertSame([ConnectionsController::redirectUri()], $document['redirect_uris']);
    }

    public function test_people_who_cannot_manage_agents_cannot_see_connections(): void
    {
        $this->add();
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->assertSame(403, $this->call('GET', 'connections')->get_status());
    }

    public function test_deleting_forgets_the_saved_key(): void
    {
        $this->server->token = 'sk';
        $connection = $this->add();
        $this->call('POST', 'connections/' . $connection['id'] . '/key', ['key' => 'sk']);

        $this->call('DELETE', 'connections/' . $connection['id']);

        $this->assertSame([], array_values(array_filter(EncryptedOptionSecretStore::names(), static fn (string $n): bool => str_starts_with($n, 'connection-'))));
        $this->assertSame(404, $this->call('GET', 'connections/' . $connection['id'])->get_status());
    }

    public function test_an_agent_uses_a_connected_tool(): void
    {
        $this->add();
        $agentId = $this->createAgent(['abilities' => ['mcp-issues/search_issues'], 'approval' => 'writes']);
        $this->model->replyWithToolCall($this->toolName('mcp-issues/search_issues'), ['q' => 'login bug'])->replyWithText('Found it.');
        $run = new Run(null, $agentId, TriggerType::Manual, [], time());
        $this->runs->add($run);

        $this->executor()->handle((int) $run->id());

        $run = $this->runs->find((int) $run->id());
        $this->assertSame(RunStatus::Completed, $run?->status());
        $this->assertSame('search_issues', $this->server->calls[0]['name']);
        $this->assertSame(['q' => 'login bug'], $this->server->calls[0]['arguments']);
    }

    public function test_a_connected_tool_that_acts_outside_the_site_waits_for_approval(): void
    {
        $this->add();
        $agentId = $this->createAgent(['abilities' => ['mcp-issues/create_issue'], 'approval' => 'writes']);
        $this->model->replyWithToolCall($this->toolName('mcp-issues/create_issue'), ['title' => 'Bug']);
        $run = new Run(null, $agentId, TriggerType::Manual, [], time());
        $this->runs->add($run);

        $this->executor()->handle((int) $run->id());

        $this->assertSame(RunStatus::AwaitingApproval, $this->runs->find((int) $run->id())?->status());
        $this->assertSame([], $this->server->calls);
    }

    public function test_secrets_are_encrypted_and_not_autoloaded(): void
    {
        $store = new EncryptedOptionSecretStore();
        $store->set('test-secret', 'hunter2');

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", 'agent_studio_secret_' . md5('test-secret')));

        $this->assertStringNotContainsString('hunter2', (string) $row->option_value);
        $this->assertContains($row->autoload, ['no', 'off']);
        $this->assertSame('hunter2', $store->get('test-secret'));

        $store->delete('test-secret');
        $this->assertNull($store->get('test-secret'));
    }
}
