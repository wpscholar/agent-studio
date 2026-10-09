<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\WordPress\Plugin;
use AgentStudio\WordPress\Rest\ChannelsController;

/**
 * Slack end to end against a fake Slack API: guided setup, OAuth connect,
 * signed events, threads, and approving with a button.
 */
final class SlackChannelTest extends IntegrationTestCase
{
    private const SIGNING_SECRET = 'signing-secret-123';

    /** @var list<array{url: string, body: string, headers: array<string, string>}> */
    private array $sent = [];

    private int $agentId = 0;

    public function set_up(): void
    {
        parent::set_up();

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        add_filter('pre_http_request', [$this, 'answer'], 10, 3);
        $this->agentId = $this->createAgent(['abilities' => ['agent-studio/add-post-note'], 'approval' => 'writes'], 'Helper');
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
        $this->sent[] = ['url' => $url, 'body' => (string) ($args['body'] ?? ''), 'headers' => (array) ($args['headers'] ?? [])];

        $method = str_replace('https://slack.com/api/', '', $url);
        $json = match ($method) {
            'oauth.v2.access' => ['ok' => true, 'access_token' => 'xoxb-test', 'app_id' => 'A1', 'bot_user_id' => 'UBOT', 'team' => ['id' => 'T1', 'name' => 'Acme']],
            'auth.test' => ['ok' => true, 'user_id' => 'UBOT', 'team' => 'Acme'],
            'users.info' => ['ok' => true, 'user' => ['name' => 'ann', 'profile' => ['display_name' => 'Ann']]],
            default => ['ok' => true],
        };

        return ['headers' => ['content-type' => 'application/json'], 'body' => (string) wp_json_encode($json), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
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

    private function slack(int $channelId, string $body, string $contentType = 'application/json', ?int $timestamp = null, ?string $secret = null): WP_REST_Response
    {
        $timestamp ??= time();
        $request = new WP_REST_Request('POST', '/agent-studio/v1/channels/' . $channelId . '/inbound');
        $request->set_header('Content-Type', $contentType);
        $request->set_header('X-Slack-Request-Timestamp', (string) $timestamp);
        $request->set_header('X-Slack-Signature', 'v0=' . hash_hmac('sha256', 'v0:' . $timestamp . ':' . $body, $secret ?? self::SIGNING_SECRET));
        $request->set_body($body);

        return rest_do_request($request);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function event(int $channelId, array $event, string $id): WP_REST_Response
    {
        return $this->slack($channelId, (string) wp_json_encode(['type' => 'event_callback', 'event_id' => $id, 'team_id' => 'T1', 'event' => $event]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function posted(): array
    {
        $posts = [];
        foreach ($this->sent as $sent) {
            if ($sent['url'] === 'https://slack.com/api/chat.postMessage') {
                $posts[] = (array) json_decode($sent['body'], true);
            }
        }

        return $posts;
    }

    private function connectedChannel(): int
    {
        $created = $this->call('POST', 'channels', ['type' => 'slack', 'name' => 'Helper bot', 'agent_id' => $this->agentId]);
        $this->assertSame(201, $created->get_status(), (string) wp_json_encode($created->get_data()));
        $id = (int) $created->get_data()['id'];

        $this->call('PUT', 'channels/' . $id, ['settings' => ['client_id' => '111.222'], 'secrets' => ['client_secret' => 'cs', 'signing_secret' => self::SIGNING_SECRET]]);
        $url = $this->call('POST', 'channels/' . $id . '/authorize')->get_data()['url'];
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

        $_GET = ['action' => ChannelsController::CONNECTED_ACTION, 'state' => $query['state'], 'code' => 'abc'];
        $this->redirectOf(static fn () => Plugin::container()->get(ChannelsController::class)->connected());

        return $id;
    }

    private function pair(int $channelId): void
    {
        $code = $this->call('POST', 'channels/' . $channelId . '/pairing')->get_data()['code'];
        $this->event($channelId, ['type' => 'message', 'channel_type' => 'im', 'channel' => 'D1', 'user' => 'U1', 'text' => $code, 'ts' => '1.0'], 'Ev0');
    }

    public function test_setup_continues_on_the_channel_page_with_a_prefilled_app(): void
    {
        $created = $this->call('POST', 'channels', ['type' => 'slack', 'name' => 'Helper bot', 'agent_id' => $this->agentId])->get_data();

        $this->assertSame('new', $created['status']);
        $this->assertTrue($created['guided']);
        $this->assertCount(3, $created['followUp']);

        $link = $created['followUp'][0]['links'][0]['url'];
        parse_str((string) wp_parse_url($link, PHP_URL_QUERY), $query);
        $manifest = json_decode((string) $query['manifest_json'], true);
        $this->assertSame(rest_url('agent-studio/v1/channels/' . $created['id'] . '/inbound'), $manifest['settings']['event_subscriptions']['request_url']);
        $this->assertSame([admin_url('admin-post.php?action=agent_studio_channel_connected')], $manifest['oauth_config']['redirect_urls']);
        $this->assertContains('message.im', $manifest['settings']['event_subscriptions']['bot_events']);
    }

    public function test_slacks_url_check_is_answered_while_the_app_is_being_created(): void
    {
        $id = (int) $this->call('POST', 'channels', ['type' => 'slack', 'name' => 'Helper bot', 'agent_id' => $this->agentId])->get_data()['id'];

        $response = $this->slack($id, (string) wp_json_encode(['type' => 'url_verification', 'challenge' => 'abc123']), 'application/json', null, 'not-known-yet');

        $this->assertSame(['challenge' => 'abc123'], $response->get_data());
    }

    public function test_connecting_needs_the_app_credentials_first(): void
    {
        $id = (int) $this->call('POST', 'channels', ['type' => 'slack', 'name' => 'Helper bot', 'agent_id' => $this->agentId])->get_data()['id'];

        $response = $this->call('POST', 'channels/' . $id . '/authorize');

        $this->assertSame(409, $response->get_status());
        $this->assertStringContainsString('Client ID', $response->get_data()['message']);
    }

    public function test_connect_to_slack_stores_the_bot_token_and_switches_on(): void
    {
        $id = $this->connectedChannel();

        $detail = $this->call('GET', 'channels/' . $id)->get_data();
        $this->assertSame('active', $detail['status'], (string) $detail['message']);
        $this->assertSame('Acme', $detail['settings']['team_name']);
        $this->assertContains('bot_token', $detail['savedSecrets']);
        $this->assertStringNotContainsString('xoxb-test', (string) wp_json_encode($detail));

        $exchange = array_values(array_filter($this->sent, static fn (array $s): bool => str_ends_with($s['url'], 'oauth.v2.access')))[0];
        parse_str($exchange['body'], $fields);
        $this->assertSame('abc', $fields['code']);
        $this->assertSame('cs', $fields['client_secret']);
    }

    public function test_unsigned_or_stale_requests_are_refused(): void
    {
        $id = $this->connectedChannel();
        $body = (string) wp_json_encode(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel_type' => 'im', 'channel' => 'D1', 'user' => 'U9', 'text' => 'hi', 'ts' => '1.0']]);

        $this->assertSame(401, $this->slack($id, $body, 'application/json', null, 'wrong')->get_status());
        $this->assertSame(401, $this->slack($id, $body, 'application/json', time() - 600)->get_status());
        $this->assertSame([], $this->call('GET', 'channels/' . $id)->get_data()['people']);
    }

    public function test_a_direct_message_gets_an_answer_in_the_dm(): void
    {
        $id = $this->connectedChannel();
        $this->pair($id);

        $this->event($id, ['type' => 'message', 'channel_type' => 'im', 'channel' => 'D1', 'user' => 'U1', 'text' => 'Hello there', 'ts' => '2.0'], 'Ev1');
        $run = $this->runs->recent(1, 0, $this->agentId)[0];
        $this->assertSame('Ann', $run->input()['from']);
        $this->model->replyWithText('Hi Ann!');
        $this->runChat((int) $run->id());

        $last = $this->posted()[count($this->posted()) - 1];
        $this->assertSame('D1', $last['channel']);
        $this->assertSame('Hi Ann!', $last['text']);
        $this->assertArrayNotHasKey('thread_ts', $last);
    }

    public function test_a_mention_in_a_channel_is_answered_in_its_thread(): void
    {
        $id = $this->connectedChannel();
        $this->pair($id);

        $this->event($id, ['type' => 'app_mention', 'channel' => 'C1', 'user' => 'U1', 'text' => '<@UBOT> summarize this', 'ts' => '5.5'], 'Ev2');
        $run = $this->runs->recent(1, 0, $this->agentId)[0];
        $this->assertSame('summarize this', $run->input()['text']);
        $this->model->replyWithText('Done.');
        $this->runChat((int) $run->id());

        $last = $this->posted()[count($this->posted()) - 1];
        $this->assertSame('C1', $last['channel']);
        $this->assertSame('5.5', $last['thread_ts']);
    }

    public function test_the_bots_own_messages_are_ignored(): void
    {
        $id = $this->connectedChannel();
        $this->pair($id);

        $this->event($id, ['type' => 'message', 'channel_type' => 'im', 'channel' => 'D1', 'user' => 'UBOT', 'text' => 'echo', 'ts' => '3.0'], 'Ev3');
        $this->event($id, ['type' => 'message', 'channel_type' => 'im', 'channel' => 'D1', 'user' => 'U1', 'bot_id' => 'B1', 'text' => 'echo', 'ts' => '3.1'], 'Ev4');
        $this->event($id, ['type' => 'message', 'subtype' => 'message_changed', 'channel_type' => 'im', 'channel' => 'D1', 'user' => 'U1', 'text' => 'edit', 'ts' => '3.2'], 'Ev5');

        $this->assertSame([], $this->runs->recent(10, 0, $this->agentId));
    }

    public function test_approving_with_a_slack_button(): void
    {
        $postId = self::factory()->post->create();
        $id = $this->connectedChannel();
        $this->pair($id);

        $this->event($id, ['type' => 'message', 'channel_type' => 'im', 'channel' => 'D1', 'user' => 'U1', 'text' => 'note please', 'ts' => '4.0'], 'Ev6');
        $run = $this->runs->recent(1, 0, $this->agentId)[0];
        $this->model->replyWithToolCall($this->toolName('agent-studio/add-post-note'), ['post_id' => $postId, 'note' => 'ok']);
        $this->runChat((int) $run->id());

        $ask = $this->posted()[count($this->posted()) - 1];
        $buttons = $ask['blocks'][1]['elements'];
        $this->assertSame(['primary', 'danger'], array_column($buttons, 'style'));

        $payload = (string) wp_json_encode([
            'type' => 'block_actions',
            'user' => ['id' => 'U1', 'name' => 'ann'],
            'channel' => ['id' => 'D1'],
            'response_url' => 'https://hooks.slack.com/actions/T1/1/xyz',
            'actions' => [['action_id' => 'agent_studio_approve', 'value' => $buttons[0]['value']]],
        ]);
        $this->slack($id, http_build_query(['payload' => $payload]), 'application/x-www-form-urlencoded');

        $this->assertSame(RunStatus::Queued, $this->runs->find((int) $run->id())?->status());
        $replaced = array_values(array_filter($this->sent, static fn (array $s): bool => str_starts_with($s['url'], 'https://hooks.slack.com/')));
        $this->assertTrue(json_decode($replaced[0]['body'], true)['replace_original']);
    }

    public function test_pairing_points_people_at_the_app_in_slack(): void
    {
        $id = $this->connectedChannel();

        $pairing = $this->call('POST', 'channels/' . $id . '/pairing')->get_data();

        $this->assertSame('https://slack.com/app_redirect?app=A1&team=T1', $pairing['link']);
        $this->assertSame($pairing['code'], $pairing['text']);
    }

    public function test_notifications_go_to_the_owners_dm_once_linked(): void
    {
        $id = $this->connectedChannel();
        $this->pair($id);
        $other = $this->createAgent(['abilities' => ['agent-studio/add-post-note'], 'approval' => 'writes'], 'Other');
        $postId = self::factory()->post->create();

        $this->model->replyWithToolCall($this->toolName('agent-studio/add-post-note'), ['post_id' => $postId, 'note' => 'x']);
        $run = new Run(null, $other, \AgentStudio\Domain\Agent\TriggerType::Manual, [], time());
        $this->runs->add($run);
        $this->runChat((int) $run->id());

        $last = $this->posted()[count($this->posted()) - 1];
        $this->assertSame('D1', $last['channel']);
        $this->assertStringContainsString('Other', $last['text']);
    }
}
