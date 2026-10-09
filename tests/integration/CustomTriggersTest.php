<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\Application\Triggers\TriggerDefinitions;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Infrastructure\AgentConfigMapper;
use AgentStudio\Tests\Support\OrderPlacedTrigger;
use AgentStudio\WordPress\Admin\AgentEditor;
use AgentStudio\WordPress\Admin\TemplatesPage;
use AgentStudio\WordPress\Plugin;

final class CustomTriggersTest extends IntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        $definitions = Plugin::container()->get(TriggerDefinitions::class);
        if ($definitions->get('test-shop/order-placed') === null) {
            $definitions->register(new OrderPlacedTrigger());
        }
    }

    public function test_a_plugin_event_starts_matching_agents(): void
    {
        $all = $this->createAgent(['triggers' => [['type' => 'custom', 'config' => ['kind' => 'test-shop/order-placed']]]], 'All orders');
        $big = $this->createAgent(['triggers' => [['type' => 'custom', 'config' => ['kind' => 'test-shop/order-placed', 'min_total' => '200']]]], 'Big orders');

        do_action('agent_studio_trigger', 'test-shop/order-placed', ['order_id' => 7, 'total' => 150], 'order-7');
        do_action('agent_studio_trigger', 'test-shop/order-placed', ['order_id' => 7, 'total' => 150], 'order-7');

        $runs = $this->runs->recent(10, 0, $all);
        $this->assertCount(1, $runs, 'The same key starts an agent once.');
        $this->assertSame(TriggerType::Custom, $runs[0]->triggerType());
        $this->assertSame('Order #7 was placed.', $runs[0]->input()['about']);
        $this->assertSame([], $this->runs->recent(10, 0, $big));
    }

    public function test_unknown_events_are_ignored(): void
    {
        $id = $this->createAgent(['triggers' => [['type' => 'custom', 'config' => ['kind' => 'nobody/registered-this']]]]);

        do_action('agent_studio_trigger', 'nobody/registered-this', ['x' => 1]);

        $this->assertSame([], $this->runs->recent(10, 0, $id));
    }

    public function test_the_editor_shows_plugin_events_with_their_fields_and_saves_them(): void
    {
        $id = $this->createAgent([]);
        $editor = Plugin::container()->get(AgentEditor::class);

        ob_start();
        $editor->triggersBox(get_post($id));
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('An order is placed', $html);
        $this->assertStringContainsString('name="agent_studio[triggers][custom:test-shop/order-placed][min_total]"', $html);

        $_POST = ['agent_studio_nonce' => wp_create_nonce('agent_studio_save_agent_' . $id), 'agent_studio' => [
            'status' => 'active',
            'triggers' => [
                'custom:test-shop/order-placed' => ['on' => '1', 'min_total' => '50'],
                'post_status' => ['on' => '1'],
            ],
        ]];
        $editor->save($id, get_post($id));

        $triggers = AgentConfigMapper::normalize(AgentConfigMapper::read($id))['triggers'];
        $this->assertContains(['type' => 'custom', 'config' => ['kind' => 'test-shop/order-placed', 'min_total' => '50']], $triggers);
        $this->assertCount(2, $triggers);
    }

    public function test_a_plugin_template_can_use_a_plugin_trigger(): void
    {
        add_filter('agent_studio_agent_templates', static function (array $templates): array {
            $templates['big-order-thanks'] = [
                'title' => 'Big order thank-you',
                'summary' => 'Thanks big spenders.',
                'instructions' => 'Write a thank-you.',
                'config' => ['triggers' => [['type' => 'custom', 'config' => ['kind' => 'test-shop/order-placed', 'min_total' => '500']]]],
            ];

            return $templates;
        });
        $_POST = $_REQUEST = ['template' => 'big-order-thanks', '_wpnonce' => wp_create_nonce('agent_studio_create_from_template')];

        $this->redirectOf(static fn () => Plugin::container()->get(TemplatesPage::class)->create());

        $agents = array_values(array_filter((new \AgentStudio\Infrastructure\WordPressAgentRepository())->all(), static fn ($a): bool => $a->name === 'Big order thank-you'));
        $this->assertSame('test-shop/order-placed', $agents[0]->triggers[0]->kind());
        $this->assertSame('500', $agents[0]->triggers[0]->config['min_total']);
        $this->assertFalse($agents[0]->isActive(), 'Templates create paused agents.');
        remove_all_filters('agent_studio_agent_templates');
    }
}
