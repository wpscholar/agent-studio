<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use AgentStudio\Application\Channels\ChannelService;
use AgentStudio\Application\Channels\ConversationReplies;
use AgentStudio\Application\Conversations\ConversationService;
use AgentStudio\Application\Execute\ExecuteRunHandler;
use AgentStudio\Application\Execute\ToolExecutor;
use AgentStudio\Application\Execute\ToolRegistryFactory;
use AgentStudio\Application\Prompt\PromptBuilder;
use AgentStudio\Application\Approval\ApprovalTokens;
use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\ApprovalPolicy;
use AgentStudio\Contracts\Channel\ChannelPhrases;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Contracts\Notifier;
use AgentStudio\Contracts\RecursionGuard;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\Infrastructure\SystemClock;
use AgentStudio\Infrastructure\WordPressAgentRepository;
use AgentStudio\Infrastructure\WordPressIdentity;
use AgentStudio\Infrastructure\WordPressSkillRepository;
use AgentStudio\Infrastructure\WpdbConversationRepository;
use AgentStudio\WordPress\Plugin;

/**
 * Channels end to end against fake Telegram and ntfy servers, answered through
 * the WordPress HTTP API.
 */
final class ChannelsTest extends IntegrationTestCase
{
    private const TOKEN = '123456:ABCDEFGHIJKLMNOPQRSTUVWX';

    /** @var list<array{url: string, body: array<string, mixed>}> */
    private array $sent = [];

    private int $agentId = 0;

    public function set_up(): void
    {
        parent::set_up();

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        add_filter('pre_http_request', [$this, 'answer'], 10, 3);
        // Telegram only calls https sites.
        add_filter('rest_url', [$this, 'https']);

        $this->agentId = $this->createAgent(['abilities' => ['agent-studio/add-post-note'], 'approval' => 'writes'], 'Helper');
    }

    public function tear_down(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        remove_filter('pre_http_request', [$this, 'answer'], 10);
        remove_filter('rest_url', [$this, 'https']);

        parent::tear_down();
    }

    public function https(string $url): string
    {
        return str_replace('http://', 'https://', $url);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function answer(mixed $pre, array $args, string $url): array
    {
        $body = json_decode((string) ($args['body'] ?? ''), true);
        $this->sent[] = ['url' => $url, 'body' => is_array($body) ? $body : []];

        $result = str_ends_with($url, '/getMe') ? ['id' => 1, 'username' => 'acme_bot', 'first_name' => 'Acme'] : true;
        $json = str_starts_with($url, 'https://api.telegram.org/') ? ['ok' => true, 'result' => $result] : ['id' => 'n1'];

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

    /**
     * Sends an update as Telegram would.
     *
     * @param array<string, mixed> $update
     */
    private function telegram(int $channelId, array $update, ?string $secret = null): WP_REST_Response
    {
        $request = new WP_REST_Request('POST', '/agent-studio/v1/channels/' . $channelId . '/inbound');
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', $secret ?? hash_hmac('sha256', 'agent-studio-telegram-webhook', self::TOKEN));
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($update));

        return rest_do_request($request);
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(int $userId, string $text, int $messageId): array
    {
        return ['update_id' => $messageId, 'message' => [
            'message_id' => $messageId,
            'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Ann'],
            'chat' => ['id' => $userId, 'type' => 'private'],
            'text' => $text,
        ]];
    }

    /**
     * @return list<array{url: string, body: array<string, mixed>}>
     */
    private function telegramCalls(string $method): array
    {
        return array_values(array_filter($this->sent, static fn (array $s): bool => str_ends_with($s['url'], '/' . $method)));
    }

    private function addTelegram(): int
    {
        $response = $this->call('POST', 'channels', ['type' => 'telegram', 'name' => 'Support bot', 'agent_id' => $this->agentId, 'secrets' => ['token' => self::TOKEN]]);
        $this->assertSame(201, $response->get_status(), (string) wp_json_encode($response->get_data()));
        $this->assertSame('active', $response->get_data()['status'], (string) $response->get_data()['message']);

        return (int) $response->get_data()['id'];
    }

    private function pair(int $channelId, int $telegramUser = 100): void
    {
        $code = $this->call('POST', 'channels/' . $channelId . '/pairing')->get_data()['code'];
        $this->telegram($channelId, self::text($telegramUser, '/start ' . $code, 1));
    }

    /**
     * Runs a queued run with the scripted model, wired like the plugin, so replies go back to the chat.
     */
    private function runChat(int $runId): void
    {
        $c = Plugin::container();
        $gateway = $c->get(AbilityGateway::class);
        $identity = new WordPressIdentity();
        $guard = $c->get(RecursionGuard::class);
        $clock = new SystemClock();
        $conversations = new ConversationService(new WpdbConversationRepository(), $this->model, $clock);

        (new ExecuteRunHandler(
            new WordPressAgentRepository(),
            $this->runs,
            new ToolRegistryFactory($gateway, new WordPressSkillRepository()),
            $this->model,
            new ToolExecutor($gateway, $identity, $guard, $clock),
            new PromptBuilder(),
            $c->get(Notifier::class),
            $identity,
            $guard,
            $clock,
            $conversations,
            new ConversationReplies(
                $conversations,
                $c->get(ChannelService::class),
                $c->get(ChannelRepository::class),
                $c->get(ContactRepository::class),
                $c->get(AgentRepository::class),
                $c->get(ApprovalPolicy::class),
                $c->get(ApprovalTokens::class),
                $c->get(ChannelPhrases::class)
            )
        ))->handle($runId);
    }

    private function lastRun(): Run
    {
        $run = $this->runs->recent(1, 0, $this->agentId)[0] ?? null;
        $this->assertNotNull($run);

        return $run;
    }

    public function test_setting_up_telegram_registers_a_signed_webhook(): void
    {
        $channelId = $this->addTelegram();

        $webhook = $this->telegramCalls('setWebhook')[0]['body'];
        $this->assertSame(rest_url('agent-studio/v1/channels/' . $channelId . '/inbound'), $webhook['url']);
        $this->assertSame(hash_hmac('sha256', 'agent-studio-telegram-webhook', self::TOKEN), $webhook['secret_token']);
        $this->assertSame('acme_bot', $this->call('GET', 'channels/' . $channelId)->get_data()['settings']['bot_username']);
        $this->assertStringNotContainsString(self::TOKEN, (string) wp_json_encode($this->call('GET', 'channels/' . $channelId)->get_data()));
    }

    public function test_requests_without_telegrams_signature_are_refused(): void
    {
        $channelId = $this->addTelegram();

        $this->assertSame(401, $this->telegram($channelId, self::text(5, 'hi', 1), 'wrong')->get_status());
        $this->assertSame([], $this->call('GET', 'channels/' . $channelId)->get_data()['people']);
    }

    public function test_strangers_wait_to_be_let_in_then_can_chat(): void
    {
        $channelId = $this->addTelegram();

        $this->telegram($channelId, self::text(5, 'hi', 1));
        $this->telegram($channelId, self::text(5, 'hello?', 2));

        $people = $this->call('GET', 'channels/' . $channelId)->get_data()['people'];
        $this->assertSame('pending', $people[0]['status']);
        $this->assertCount(1, $this->telegramCalls('sendMessage'), 'The wait message is sent once.');
        $this->assertSame([], $this->runs->recent(10, 0, $this->agentId));

        $this->call('PUT', 'channels/' . $channelId . '/contacts/' . $people[0]['id'], ['status' => 'allowed']);
        $this->telegram($channelId, self::text(5, 'Can you help?', 3));

        $run = $this->lastRun();
        $this->assertSame(TriggerType::Message, $run->triggerType());
        $this->assertSame('Can you help?', $run->input()['text']);
    }

    public function test_pairing_links_the_chat_to_the_wordpress_account(): void
    {
        $channelId = $this->addTelegram();

        $pairing = $this->call('POST', 'channels/' . $channelId . '/pairing')->get_data();
        $this->assertSame('https://t.me/acme_bot?start=' . $pairing['code'], $pairing['link']);

        $this->telegram($channelId, self::text(100, '/start ' . $pairing['code'], 1));

        $detail = $this->call('GET', 'channels/' . $channelId)->get_data();
        $this->assertSame('allowed', $detail['people'][0]['status']);
        $this->assertSame($this->adminId, $detail['people'][0]['user']['id']);
        $this->assertTrue($detail['notifyReady'], 'The owner\'s chat now gets notifications.');
        $this->assertTrue($detail['linkedToMe']);
    }

    public function test_a_conversation_gets_answers_and_remembers_earlier_messages(): void
    {
        $channelId = $this->addTelegram();
        $this->pair($channelId);

        $this->telegram($channelId, self::text(100, 'My name is Ann.', 2));
        $this->model->replyWithText('Nice to meet you, Ann!');
        $this->runChat((int) $this->lastRun()->id());

        $this->telegram($channelId, self::text(100, 'What is my name?', 3));
        $this->model->replyWithText('Your name is Ann.');
        $this->runChat((int) $this->lastRun()->id());

        $replies = array_column(array_column($this->telegramCalls('sendMessage'), 'body'), 'text');
        $this->assertContains('Nice to meet you, Ann!', $replies);
        $this->assertContains('Your name is Ann.', $replies);

        $seen = $this->model->requests[1]->messages;
        $this->assertStringContainsString('My name is Ann.', $seen[0]->content);
        $this->assertSame('Nice to meet you, Ann!', $seen[1]->content);
        $this->assertStringContainsString('What is my name?', $seen[2]->content);
        $this->assertStringContainsString('chatting with people through Support bot', $this->model->requests[1]->systemPrompt);
    }

    public function test_the_same_message_delivered_twice_is_answered_once(): void
    {
        $channelId = $this->addTelegram();
        $this->pair($channelId);

        $this->telegram($channelId, self::text(100, 'Hello', 2));
        $this->telegram($channelId, self::text(100, 'Hello', 2));

        $this->assertCount(1, $this->runs->recent(10, 0, $this->agentId));
    }

    public function test_approving_from_telegram(): void
    {
        $postId = self::factory()->post->create();
        $channelId = $this->addTelegram();
        $this->pair($channelId);

        $this->telegram($channelId, self::text(100, 'Leave a note on the post', 2));
        $this->model->replyWithToolCall($this->toolName('agent-studio/add-post-note'), ['post_id' => $postId, 'note' => 'Looks good']);
        $runId = (int) $this->lastRun()->id();
        $this->runChat($runId);

        $this->assertSame(RunStatus::AwaitingApproval, $this->runs->find($runId)?->status());
        $ask = $this->telegramCalls('sendMessage');
        $keyboard = end($ask)['body']['reply_markup']['inline_keyboard'][0] ?? [];
        $this->assertSame(['Approve', 'Reject'], array_column($keyboard, 'text'));

        $this->telegram($channelId, ['update_id' => 9, 'callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => 100, 'first_name' => 'Ann'],
            'message' => ['message_id' => 50, 'chat' => ['id' => 100]],
            'data' => $keyboard[0]['callback_data'],
        ]]);

        $this->assertSame(RunStatus::Queued, $this->runs->find($runId)?->status());
        $this->assertSame('cb1', $this->telegramCalls('answerCallbackQuery')[0]['body']['callback_query_id']);

        $this->model->replyWithText('Note added.');
        $this->runChat($runId);
        $this->assertSame(RunStatus::Completed, $this->runs->find($runId)?->status());
        $this->assertNotEmpty(get_post_meta($postId, '_agent_studio_notes', true));
    }

    public function test_someone_not_linked_cannot_approve_from_the_chat(): void
    {
        $postId = self::factory()->post->create();
        $channelId = $this->addTelegram();
        $this->pair($channelId);

        $this->telegram($channelId, self::text(100, 'note please', 2));
        $this->model->replyWithToolCall($this->toolName('agent-studio/add-post-note'), ['post_id' => $postId, 'note' => 'x']);
        $runId = (int) $this->lastRun()->id();
        $this->runChat($runId);
        $ask = $this->telegramCalls('sendMessage');
        $value = end($ask)['body']['reply_markup']['inline_keyboard'][0][0]['callback_data'];

        $this->telegram($channelId, ['update_id' => 9, 'callback_query' => [
            'id' => 'cb2',
            'from' => ['id' => 666, 'first_name' => 'Mallory'],
            'message' => ['message_id' => 50, 'chat' => ['id' => 100]],
            'data' => $value,
        ]]);

        $this->assertSame(RunStatus::AwaitingApproval, $this->runs->find($runId)?->status());
    }

    public function test_ntfy_sends_tests_and_approval_requests_with_signed_buttons(): void
    {
        $response = $this->call('POST', 'channels', ['type' => 'ntfy', 'name' => 'My phone', 'settings' => ['topic' => 'agent-studio-test', 'server' => 'https://ntfy.sh']]);
        $this->assertSame('active', $response->get_data()['status']);
        $channelId = (int) $response->get_data()['id'];

        $this->assertSame(200, $this->call('POST', 'channels/' . $channelId . '/test')->get_status());
        $this->assertSame('agent-studio-test', end($this->sent)['body']['topic']);

        $postId = self::factory()->post->create();
        $this->model->replyWithToolCall($this->toolName('agent-studio/add-post-note'), ['post_id' => $postId, 'note' => 'x']);
        $run = new Run(null, $this->agentId, TriggerType::Manual, [], time());
        $this->runs->add($run);
        $this->runChat((int) $run->id());

        $request = end($this->sent);
        $this->assertSame('https://ntfy.sh/', $request['url']);
        $actions = $request['body']['actions'];
        $this->assertSame(['http', 'http', 'view'], array_column($actions, 'action'));
        $approve = $actions[0]['url'];

        $parts = wp_parse_url($approve);
        parse_str((string) ($parts['query'] ?? ''), $query);
        // Without pretty permalinks the route travels in ?rest_route=.
        $route = isset($query['rest_route']) ? (string) $query['rest_route'] : (string) $parts['path'];
        unset($query['rest_route']);
        $path = substr($route, (int) strpos($route, '/channels/'));

        $tampered = $this->call('POST', ltrim($path, '/'), ['decision' => 'approve', 'token' => str_repeat('0', 64)]);
        $this->assertSame(403, $tampered->get_status());

        wp_set_current_user(0);
        $decided = $this->call('POST', ltrim($path, '/'), $query);
        $this->assertSame(200, $decided->get_status());
        $this->assertSame(RunStatus::Queued, $this->runs->find((int) $run->id())?->status());
    }

    public function test_deleting_a_channel_switches_it_off_and_forgets_its_people(): void
    {
        $channelId = $this->addTelegram();
        $this->telegram($channelId, self::text(5, 'hi', 1));

        $this->call('DELETE', 'channels/' . $channelId);

        $this->assertNotEmpty($this->telegramCalls('deleteWebhook'));
        $this->assertSame(404, $this->call('GET', 'channels/' . $channelId)->get_status());
        $this->assertSame([], Plugin::container()->get(ContactRepository::class)->forChannel($channelId));
    }

    public function test_a_chat_channel_needs_an_agent(): void
    {
        $response = $this->call('POST', 'channels', ['type' => 'telegram', 'name' => 'Bot', 'secrets' => ['token' => self::TOKEN]]);

        $this->assertSame(400, $response->get_status());
        $this->assertStringContainsString('Choose which agent', $response->get_data()['message']);
    }

    public function test_listing_agents_for_the_screen(): void
    {
        $agents = $this->call('GET', 'agents')->get_data();

        $this->assertSame('Helper', $agents[0]['name']);
        $this->assertTrue($agents[0]['active']);
    }
}
