<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\WordPress\Admin\OldScreens;
use AgentStudio\WordPress\Admin\RunsPage;
use AgentStudio\WordPress\Agents\Readiness;
use AgentStudio\WordPress\Plugin;
use AgentStudio\WordPress\Urls;

final class AdminTest extends IntegrationTestCase
{
    private function render(callable $callback): string
    {
        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    public function test_the_runs_page_escapes_what_came_from_outside(): void
    {
        $agentId = $this->createAgent(['triggers' => [['type' => 'webhook']], 'webhook_secret' => 'abc']);
        $run = new \AgentStudio\Domain\Run\Run(null, $agentId, \AgentStudio\Domain\Agent\TriggerType::Webhook, ['x' => '<script>alert(1)</script>'], time());
        $this->runs->add($run);
        $_GET = ['run' => (string) $run->id()];

        $html = $this->render(static fn () => Plugin::container()->get(RunsPage::class)->render());

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }

    public function test_links_to_the_old_screens_go_to_the_new_ones(): void
    {
        global $pagenow;
        $agentId = $this->createAgent([]);

        $cases = [
            ['edit.php', ['post_type' => 'agent_studio_agent'], Urls::agents()],
            ['post-new.php', ['post_type' => 'agent_studio_agent'], Urls::newAgent()],
            ['post.php', ['post' => (string) $agentId, 'action' => 'edit'], Urls::editAgent($agentId)],
            ['edit.php', ['post_type' => 'agent_studio_agent', 'page' => 'agent-studio-templates'], Urls::newAgent()],
            ['edit.php', ['post_type' => 'agent_studio_agent', 'page' => 'agent-studio-runs', 'run' => '5'], add_query_arg('run', '5', Urls::runs())],
        ];

        foreach ($cases as [$page, $query, $expected]) {
            $pagenow = $page;
            $_GET = $query;
            $this->assertSame($expected, OldScreens::target(), $page . ' ' . http_build_query($query));
        }

        $pagenow = 'post.php';
        $_GET = ['post' => (string) self::factory()->post->create(), 'action' => 'edit'];
        $this->assertNull(OldScreens::target(), 'Other posts open as usual.');
    }

    public function test_readiness_reports_a_missing_ai_provider(): void
    {
        $result = (new Readiness(static fn (): array => []))->check();
        $checks = array_column($result['checks'], null, 'key');

        $this->assertFalse($result['ready']);
        $this->assertSame('error', $checks['provider']['status']);
        $this->assertStringContainsString('options-connectors.php', (string) $checks['provider']['action']['url']);
        $this->assertSame('error', $checks['tool_model']['status']);
    }

    public function test_readiness_needs_a_model_that_can_use_abilities(): void
    {
        $withoutTools = static fn (): array => [['id' => 'acme', 'name' => 'Acme', 'models' => [['id' => 'm', 'name' => 'M', 'tools' => false]]]];
        $withTools = static fn (): array => [['id' => 'acme', 'name' => 'Acme', 'models' => [['id' => 'm', 'name' => 'M', 'tools' => true]]]];

        $checks = array_column((new Readiness($withoutTools))->check()['checks'], 'status', 'key');
        $this->assertSame(['ok', 'error'], [$checks['provider'], $checks['tool_model']]);

        $checks = array_column((new Readiness($withTools))->check()['checks'], 'status', 'key');
        $this->assertSame(['ok', 'ok'], [$checks['provider'], $checks['tool_model']]);
    }
}
