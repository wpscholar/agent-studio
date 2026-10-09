<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;
use AgentStudio\Application\Approval\ResolveApprovalHandler;
use AgentStudio\Application\Execute\ExecuteRunHandler;
use AgentStudio\Application\Execute\ToolExecutor;
use AgentStudio\Application\Execute\ToolRegistryFactory;
use AgentStudio\Application\Prompt\PromptBuilder;
use AgentStudio\Contracts\Notifier;
use AgentStudio\Contracts\RecursionGuard;
use AgentStudio\Infrastructure\AbilitiesApiGateway;
use AgentStudio\Infrastructure\AgentConfigMapper;
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
        $id = wp_insert_post(['post_type' => 'agent_studio_agent', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $instructions]);
        AgentConfigMapper::write($id, array_merge(['status' => 'active', 'run_as' => $this->adminId], $config));

        return (int) $id;
    }

    protected function executor(): ExecuteRunHandler
    {
        $gateway = new AbilitiesApiGateway();
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
        $gateway = new AbilitiesApiGateway();
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
        foreach ((new AbilitiesApiGateway())->available() as $info) {
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
}
