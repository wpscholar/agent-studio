<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;
use AgentStudio\Application\Approval\ResolveApprovalHandler;
use AgentStudio\Application\Approval\ApprovalTokens;
use AgentStudio\Application\Channels\ChannelService;
use AgentStudio\Application\Channels\ConversationReplies;
use AgentStudio\Application\Conversations\ConversationService;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\ApprovalPolicy;
use AgentStudio\Contracts\Channel\ChannelPhrases;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Infrastructure\WpdbConversationRepository;
use AgentStudio\Application\Execute\ExecuteRunHandler;
use AgentStudio\Application\Execute\ToolExecutor;
use AgentStudio\Application\Execute\ToolRegistryFactory;
use AgentStudio\Application\Prompt\PromptBuilder;
use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Contracts\Notifier;
use AgentStudio\Contracts\RecursionGuard;
use AgentStudio\Domain\Agent\AgentStatus;
use AgentStudio\Infrastructure\AgentConfigMapper;
use AgentStudio\Infrastructure\AgentPostStatus;
use AgentStudio\Infrastructure\SystemClock;
use AgentStudio\Infrastructure\WordPressAgentRepository;
use AgentStudio\Infrastructure\WordPressIdentity;
use AgentStudio\Infrastructure\WordPressJobQueue;
use AgentStudio\Infrastructure\WordPressSkillRepository;
use AgentStudio\Infrastructure\WpdbRunRepository;
use AgentStudio\Tests\Support\ScriptedModelClient;
use AgentStudio\WordPress\Plugin;

/**
 * Base for tests that run against a real WordPress. They wire the real
 * repositories and the real Abilities API, and fake only the AI model.
 */
abstract class IntegrationTestCase extends WP_UnitTestCase
{
    protected ScriptedModelClient $model;
    protected WpdbRunRepository $runs;
    protected int $adminId = 0;

    public function set_up(): void
    {
        parent::set_up();

        $this->model = new ScriptedModelClient();
        $this->runs = new WpdbRunRepository();
        $this->adminId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->adminId);
    }

    public function tear_down(): void
    {
        $_POST = [];
        $_REQUEST = [];
        $_GET = [];
        remove_all_filters('wp_redirect');

        parent::tear_down();
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createAgent(array $config, string $title = 'Test agent', string $instructions = 'Do the job.'): int
    {
        // The status is the post status; "status" in $config is a shortcut for it.
        $status = AgentStatus::fromString(is_string($config['status'] ?? null) ? $config['status'] : 'active');
        unset($config['status']);

        $id = wp_insert_post(['post_type' => 'agent_studio_agent', 'post_status' => AgentPostStatus::toPost($status), 'post_title' => $title, 'post_content' => $instructions]);
        AgentConfigMapper::write($id, array_merge(['run_as' => $this->adminId], $config));

        return (int) $id;
    }

    /**
     * Calls the plugin's REST API as the current user.
     *
     * @param array<string, mixed> $body
     */
    protected function rest(string $method, string $path, array $body = []): \WP_REST_Response
    {
        $request = new \WP_REST_Request($method, '/agent-studio/v1/' . $path);
        foreach ($body as $key => $value) {
            $request->set_param($key, $value);
        }

        return rest_do_request($request);
    }

    protected function executor(): ExecuteRunHandler
    {
        // The real gateway: site abilities plus connected services' tools.
        $gateway = Plugin::container()->get(AbilityGateway::class);
        $agents = new WordPressAgentRepository();
        $registries = new ToolRegistryFactory($gateway, new WordPressSkillRepository());
        $identity = new WordPressIdentity();
        $guard = Plugin::container()->get(RecursionGuard::class);
        $clock = new SystemClock();

        return new ExecuteRunHandler(
            $agents,
            $this->runs,
            $registries,
            $this->model,
            new ToolExecutor($gateway, $identity, $guard, $clock),
            new PromptBuilder(),
            Plugin::container()->get(Notifier::class),
            $identity,
            $guard,
            $clock
        );
    }

    protected function approvals(): ResolveApprovalHandler
    {
        $gateway = Plugin::container()->get(AbilityGateway::class);
        $guard = Plugin::container()->get(RecursionGuard::class);
        $clock = new SystemClock();

        return new ResolveApprovalHandler(
            new WordPressAgentRepository(),
            $this->runs,
            new ToolRegistryFactory($gateway, new WordPressSkillRepository()),
            new ToolExecutor($gateway, new WordPressIdentity(), $guard, $clock),
            new WordPressJobQueue(),
            $clock
        );
    }

    protected function toolName(string $ability): string
    {
        foreach (Plugin::container()->get(AbilityGateway::class)->available() as $info) {
            if ($info->name === $ability) {
                return $info->toolName();
            }
        }

        throw new RuntimeException('Ability not available: ' . $ability);
    }

    /**
     * Makes redirects throw instead of exiting, and returns the target.
     *
     * @param callable():void $action
     */
    protected function redirectOf(callable $action): string
    {
        add_filter(
            'wp_redirect',
            static function (string $location): never {
                throw new RuntimeException($location);
            }
        );

        try {
            $action();
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return '';
    }

    /**
     * Runs a queued run with the scripted model, wired like the plugin, so replies go back to the chat.
     */
    protected function runChat(int $runId): void
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
}
