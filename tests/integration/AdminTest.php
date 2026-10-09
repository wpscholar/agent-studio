<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\Infrastructure\AgentConfigMapper;
use AgentStudio\WordPress\Admin\AgentEditor;
use AgentStudio\WordPress\Admin\RunsPage;
use AgentStudio\WordPress\Admin\TemplatesPage;
use AgentStudio\WordPress\Plugin;

final class AdminTest extends IntegrationTestCase
{
    private function editor(): AgentEditor
    {
        return Plugin::container()->get(AgentEditor::class);
    }

    private function render(callable $callback): string
    {
        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function save(int $agentId, array $fields): void
    {
        $_POST = ['agent_studio_nonce' => wp_create_nonce('agent_studio_save_agent_' . $agentId), 'agent_studio' => $fields];
        $this->editor()->save($agentId, get_post($agentId));
    }

    public function test_saving_stores_clean_configuration(): void
    {
        $id = $this->createAgent([]);

        $this->save($id, [
            'status' => 'active',
            'approval' => 'destructive',
            'run_as' => (string) $this->adminId,
            'max_steps' => '500',
            'max_runs_per_hour' => '0',
            'abilities' => ['agent-studio/get-post', 'bad name'],
            'triggers' => ['post_status' => ['on' => '1', 'post_types' => 'post, page'], 'bogus' => ['on' => '1']],
        ]);

        $config = AgentConfigMapper::normalize(AgentConfigMapper::read($id));
        $this->assertSame('destructive', $config['approval']);
        $this->assertSame(50, $config['max_steps']);
        $this->assertSame(1, $config['max_runs_per_hour']);
        $this->assertSame(['agent-studio/get-post'], $config['abilities']);
        $this->assertCount(1, $config['triggers']);
    }

    public function test_the_webhook_secret_is_created_once_and_kept(): void
    {
        $id = $this->createAgent([]);
        $fields = ['triggers' => ['webhook' => ['on' => '1']]];

        $this->save($id, $fields);
        $secret = AgentConfigMapper::read($id)['webhook_secret'];
        $this->save($id, $fields);

        $this->assertSame(40, strlen($secret));
        $this->assertSame($secret, AgentConfigMapper::read($id)['webhook_secret']);

        $this->save($id, $fields + ['regenerate_secret' => '1']);
        $this->assertNotSame($secret, AgentConfigMapper::read($id)['webhook_secret']);
    }

    public function test_saving_without_a_valid_nonce_changes_nothing(): void
    {
        $id = $this->createAgent(['approval' => 'writes']);
        $before = AgentConfigMapper::read($id);

        $_POST = ['agent_studio_nonce' => 'nope', 'agent_studio' => ['approval' => 'never']];
        $this->editor()->save($id, get_post($id));

        $this->assertSame($before, AgentConfigMapper::read($id));
    }

    public function test_users_who_cannot_manage_agents_cannot_save_them(): void
    {
        $id = $this->createAgent(['approval' => 'writes']);
        $before = AgentConfigMapper::read($id);
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->save($id, ['approval' => 'never']);

        $this->assertSame($before, AgentConfigMapper::read($id));
    }

    public function test_every_editor_box_renders(): void
    {
        $post = get_post($this->createAgent(['abilities' => ['agent-studio/get-post']]));

        foreach (['statusBox', 'triggersBox', 'modelBox', 'abilitiesBox', 'skillsBox', 'safetyBox', 'runBox'] as $box) {
            $html = $this->render(fn () => $this->editor()->$box($post));
            $this->assertGreaterThan(0, strlen($html), $box);
        }
    }

    public function test_the_abilities_box_ticks_the_agents_abilities_and_marks_risk(): void
    {
        $post = get_post($this->createAgent(['abilities' => ['agent-studio/get-post']]));

        $html = $this->render(fn () => $this->editor()->abilitiesBox($post));

        $this->assertMatchesRegularExpression('/value="agent-studio\/get-post"\s+checked/', $html);
        $this->assertStringContainsString('agent-studio-risk--external', $html);
    }

    public function test_a_schedule_trigger_is_saved_and_shown(): void
    {
        $id = $this->createAgent([]);

        $this->save($id, [
            'triggers' => ['schedule' => ['on' => '1', 'every' => 'weekly', 'day' => 'friday', 'at' => '17:30']],
        ]);

        $config = AgentConfigMapper::normalize(AgentConfigMapper::read($id));
        $this->assertSame(
            [['type' => 'schedule', 'config' => ['every' => 'weekly', 'at' => '17:30', 'day' => 'friday']]],
            $config['triggers']
        );

        $html = $this->render(fn () => $this->editor()->triggersBox(get_post($id)));
        $this->assertMatchesRegularExpression('/value="weekly"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="friday"\s+selected/', $html);
        $this->assertStringContainsString('value="17:30"', $html);
        $this->assertStringContainsString('site’s timezone', $html);
    }

    public function test_the_model_choice_is_automatic_or_one_exact_model(): void
    {
        $id = $this->createAgent([]);

        $html = $this->render(fn () => $this->editor()->modelBox(get_post($id)));
        $this->assertStringContainsString('Automatic (recommended)', $html);
        $this->assertStringContainsString('options-connectors.php', $html);

        $this->save($id, ['model_choice' => 'anthropic::claude-sonnet-5-5']);
        $config = AgentConfigMapper::normalize(AgentConfigMapper::read($id));
        $this->assertSame(['anthropic', 'claude-sonnet-5-5'], [$config['provider'], $config['model']]);

        // A saved model that is not connected stays selected and says so.
        $html = $this->render(fn () => $this->editor()->modelBox(get_post($id)));
        $this->assertMatchesRegularExpression('/value="anthropic::claude-sonnet-5-5" selected>claude-sonnet-5-5 \(not available right now\)/', $html);

        $this->save($id, ['model_choice' => '']);
        $config = AgentConfigMapper::normalize(AgentConfigMapper::read($id));
        $this->assertSame(['', ''], [$config['provider'], $config['model']]);
    }

    public function test_a_template_creates_a_paused_agent(): void
    {
        $_POST = $_REQUEST = ['template' => 'pre-publish-reviewer', '_wpnonce' => wp_create_nonce('agent_studio_create_from_template')];

        $location = $this->redirectOf(static fn () => Plugin::container()->get(TemplatesPage::class)->create());

        $this->assertStringContainsString('action=edit', $location);
        $agents = (new \AgentStudio\Infrastructure\WordPressAgentRepository())->all();
        $created = array_values(array_filter($agents, static fn ($a): bool => $a->name === 'Pre-publish reviewer'));
        $this->assertCount(1, $created);
        $this->assertFalse($created[0]->isActive());
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
}
