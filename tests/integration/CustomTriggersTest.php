<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\Application\Triggers\TriggerDefinitions;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Infrastructure\AgentConfigMapper;
use AgentStudio\Tests\Support\OrderPlacedTrigger;
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

    public function test_plugin_events_are_offered_with_their_fields_and_saved(): void
    {
        $found = $this->rest('GET', 'triggers', ['search' => 'order'])->get_data()['items'];
        $this->assertSame('custom:test-shop/order-placed', $found[0]['key']);
        $this->assertSame('min_total', $found[0]['fields'][0]['key']);
        $this->assertSame('Test shop', $found[0]['group']);

        $agent = $this->rest('POST', 'agents', [
            'name' => 'Orders',
            'instructions' => 'Thank people.',
            'status' => 'active',
            'triggers' => [
                ['key' => 'custom:test-shop/order-placed', 'config' => ['min_total' => '50', 'not_a_field' => 'x']],
                ['key' => 'post_status', 'config' => []],
            ],
        ])->get_data();

        $triggers = AgentConfigMapper::normalize(AgentConfigMapper::read($agent['id']))['triggers'];
        $this->assertContains(['type' => 'custom', 'config' => ['kind' => 'test-shop/order-placed', 'min_total' => '50']], $triggers);
        $this->assertCount(2, $triggers);
        $this->assertSame('An order is placed', $agent['triggerTypes']->{'custom:test-shop/order-placed'}['label']);
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
        $agent = $this->rest('GET', 'agents/new', ['template' => 'big-order-thanks'])->get_data();
        remove_all_filters('agent_studio_agent_templates');

        $this->assertSame('draft', $agent['status'], 'Templates start as drafts.');
        $this->assertSame('custom:test-shop/order-placed', $agent['triggers'][0]['key']);
        $this->assertSame('500', $agent['triggers'][0]['config']->min_total);
    }
}
