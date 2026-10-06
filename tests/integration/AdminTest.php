<?php

declare(strict_types=1);

namespace WPAgents\Tests\Integration;

use WPAgents\Infrastructure\AgentConfigMapper;
use WPAgents\WordPress\Admin\AgentEditor;
use WPAgents\WordPress\Admin\ImportSkillPage;
use WPAgents\WordPress\Admin\RunsPage;
use WPAgents\WordPress\Admin\TemplatesPage;
use WPAgents\WordPress\Plugin;

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
        $_POST = ['wpa_nonce' => wp_create_nonce('wpa_save_agent_' . $agentId), 'wpa' => $fields];
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
            'abilities' => ['wp-agents/get-post', 'bad name'],
            'triggers' => ['post_status' => ['on' => '1', 'post_types' => 'post, page'], 'bogus' => ['on' => '1']],
        ]);

        $config = AgentConfigMapper::normalize(AgentConfigMapper::read($id));
        $this->assertSame('destructive', $config['approval']);
        $this->assertSame(50, $config['max_steps']);
        $this->assertSame(1, $config['max_runs_per_hour']);
        $this->assertSame(['wp-agents/get-post'], $config['abilities']);
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

        $_POST = ['wpa_nonce' => 'nope', 'wpa' => ['approval' => 'never']];
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
        $post = get_post($this->createAgent(['abilities' => ['wp-agents/get-post']]));

        foreach (['statusBox', 'triggersBox', 'abilitiesBox', 'skillsBox', 'safetyBox', 'runBox'] as $box) {
            $html = $this->render(fn () => $this->editor()->$box($post));
            $this->assertGreaterThan(0, strlen($html), $box);
        }
    }

    public function test_the_abilities_box_ticks_the_agents_abilities_and_marks_risk(): void
    {
        $post = get_post($this->createAgent(['abilities' => ['wp-agents/get-post']]));

        $html = $this->render(fn () => $this->editor()->abilitiesBox($post));

        $this->assertMatchesRegularExpression('/value="wp-agents\/get-post"\s+checked/', $html);
        $this->assertStringContainsString('wpa-risk--external', $html);
    }

    public function test_a_template_creates_a_paused_agent(): void
    {
        $_POST = $_REQUEST = ['template' => 'pre-publish-reviewer', '_wpnonce' => wp_create_nonce('wpa_create_from_template')];

        $location = $this->redirectOf(static fn () => Plugin::container()->get(TemplatesPage::class)->create());

        $this->assertStringContainsString('action=edit', $location);
        $agents = (new \WPAgents\Infrastructure\WordPressAgentRepository())->all();
        $created = array_values(array_filter($agents, static fn ($a): bool => $a->name === 'Pre-publish reviewer'));
        $this->assertCount(1, $created);
        $this->assertFalse($created[0]->isActive());
    }

    public function test_importing_a_skill_from_markdown(): void
    {
        $markdown = "---\nname: Release notes writer\ndescription: Turns changes into friendly notes.\n---\n\nWrite in plain language.";
        $_POST = $_REQUEST = ['skill_markdown' => wp_slash($markdown), '_wpnonce' => wp_create_nonce('wpa_import_skill')];

        $this->redirectOf(static fn () => Plugin::container()->get(ImportSkillPage::class)->import());

        $skill = (new \WPAgents\Infrastructure\WordPressSkillRepository())->findBySlug('release-notes-writer');
        $this->assertNotNull($skill);
        $this->assertSame('Turns changes into friendly notes.', $skill->description);
    }

    public function test_the_runs_page_escapes_what_came_from_outside(): void
    {
        $agentId = $this->createAgent(['triggers' => [['type' => 'webhook']], 'webhook_secret' => 'abc']);
        $run = new \WPAgents\Domain\Run\Run(null, $agentId, \WPAgents\Domain\Agent\TriggerType::Webhook, ['x' => '<script>alert(1)</script>'], time());
        $this->runs->add($run);
        $_GET = ['run' => (string) $run->id()];

        $html = $this->render(static fn () => Plugin::container()->get(RunsPage::class)->render());

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }
}
