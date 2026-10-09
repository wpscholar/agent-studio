<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\Infrastructure\AgentConfigMapper;
use AgentStudio\Infrastructure\WordPressAgentRepository;

/**
 * The Agents screen's API: creating, changing and switching agents on and off.
 */
final class AgentsRestTest extends IntegrationTestCase
{
    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private function body(array $changes = []): array
    {
        return array_merge(
            [
                'name' => 'Reviewer',
                'instructions' => 'Review posts.',
                'status' => 'draft',
                'triggers' => [],
                'abilities' => [],
                'skills' => [],
                'approval' => 'writes',
                'runAs' => $this->adminId,
                'maxSteps' => 10,
                'maxRunsPerHour' => 30,
                'provider' => '',
                'model' => '',
            ],
            $changes
        );
    }

    public function test_a_new_agent_is_a_draft_owned_by_its_creator(): void
    {
        $response = $this->rest('POST', 'agents', $this->body());

        $this->assertSame(201, $response->get_status(), (string) wp_json_encode($response->get_data()));
        $agent = $response->get_data();
        $this->assertSame('draft', $agent['status']);
        $this->assertSame('draft', get_post_status($agent['id']));
        $this->assertSame($this->adminId, $agent['owner']['id']);
    }

    public function test_the_status_is_the_post_status(): void
    {
        $id = $this->rest('POST', 'agents', $this->body())->get_data()['id'];

        $this->assertSame('publish', get_post_status($this->rest('PUT', 'agents/' . $id, $this->body(['status' => 'active']))->get_data()['id']));
        $this->assertTrue((new WordPressAgentRepository())->find($id)?->isActive());

        $this->rest('PUT', 'agents/' . $id, $this->body(['status' => 'paused']));
        $this->assertSame('agent_studio_paused', get_post_status($id));
        $this->assertFalse((new WordPressAgentRepository())->find($id)?->isActive());
    }

    public function test_settings_are_cleaned_and_clamped(): void
    {
        $agent = $this->rest('POST', 'agents', $this->body([
            'approval' => 'destructive',
            'maxSteps' => '500',
            'maxRunsPerHour' => '0',
            'abilities' => ['agent-studio/get-post', 'bad name'],
            'triggers' => [['key' => 'post_status', 'config' => ['post_types' => ['post', 'page'], 'to' => ['pending']]]],
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5-5',
        ]))->get_data();

        $this->assertSame('destructive', $agent['approval']);
        $this->assertSame(50, $agent['maxSteps']);
        $this->assertSame(1, $agent['maxRunsPerHour']);
        $this->assertSame(['agent-studio/get-post'], $agent['abilities']);
        $this->assertSame('Get a post', $agent['abilityDetails'][0]['label']);
        $this->assertSame([['key' => 'post_status', 'config' => ['post_types' => ['post', 'page'], 'to' => ['pending']]]], json_decode((string) wp_json_encode($agent['triggers']), true));
        $this->assertSame(['anthropic', 'claude-sonnet-5-5'], [$agent['provider'], $agent['model']]);
    }

    public function test_problems_come_back_in_plain_words(): void
    {
        $this->assertSame('Give the agent a name.', $this->rest('POST', 'agents', $this->body(['name' => ' ']))->get_data()['message']);
        $this->assertSame(
            'Tell the agent what to do before switching it on.',
            $this->rest('POST', 'agents', $this->body(['status' => 'active', 'instructions' => '']))->get_data()['message']
        );
        $this->assertSame(400, $this->rest('POST', 'agents', $this->body(['triggers' => [['key' => 'manual']]]))->get_status());
        $this->assertSame(400, $this->rest('POST', 'agents', $this->body(['status' => 'sometimes']))->get_status());
    }

    public function test_a_schedule_trigger_is_cleaned(): void
    {
        $agent = $this->rest('POST', 'agents', $this->body([
            'triggers' => [['key' => 'schedule', 'config' => ['every' => 'weekly', 'day' => 'friday', 'at' => '17:30']]],
        ]))->get_data();

        $config = AgentConfigMapper::normalize(AgentConfigMapper::read($agent['id']));
        $this->assertSame([['type' => 'schedule', 'config' => ['every' => 'weekly', 'at' => '17:30', 'day' => 'friday']]], $config['triggers']);
    }

    public function test_the_webhook_secret_is_made_once_and_can_be_replaced(): void
    {
        $body = $this->body(['triggers' => [['key' => 'webhook', 'config' => []]]]);
        $agent = $this->rest('POST', 'agents', $body)->get_data();
        $secret = $agent['webhook']['secret'];

        $this->assertSame(40, strlen($secret));
        $this->assertStringContainsString('webhooks/' . $agent['id'], $agent['webhook']['url']);
        $this->assertSame($secret, $this->rest('PUT', 'agents/' . $agent['id'], $body)->get_data()['webhook']['secret']);
        $this->assertNotSame($secret, $this->rest('POST', 'agents/' . $agent['id'] . '/webhook-secret')->get_data()['webhook']['secret']);
    }

    public function test_a_template_fills_in_a_new_agent_without_saving_anything(): void
    {
        $before = count((new WordPressAgentRepository())->all());

        $agent = $this->rest('GET', 'agents/new', ['template' => 'pre-publish-reviewer'])->get_data();

        $this->assertSame('Pre-publish reviewer', $agent['name']);
        $this->assertSame('draft', $agent['status']);
        $this->assertSame('post_status', $agent['triggers'][0]['key']);
        $this->assertArrayHasKey('post_status', (array) $agent['triggerTypes']);
        $this->assertSame($before, count((new WordPressAgentRepository())->all()));
        $this->assertSame(404, $this->rest('GET', 'agents/new', ['template' => 'nope'])->get_status());
    }

    public function test_the_list_says_how_each_agent_runs(): void
    {
        $this->createAgent(['status' => 'paused', 'triggers' => [['type' => 'user_registered']]], 'Welcomer');

        $agents = $this->rest('GET', 'agents')->get_data();
        $welcomer = array_values(array_filter($agents, static fn (array $a): bool => $a['name'] === 'Welcomer'))[0];

        $this->assertSame('paused', $welcomer['status']);
        $this->assertFalse($welcomer['active']);
        $this->assertSame(['Someone joins the site'], $welcomer['triggers']);
    }

    public function test_deleting_removes_the_agent(): void
    {
        $id = $this->createAgent([]);

        $this->assertSame(200, $this->rest('DELETE', 'agents/' . $id)->get_status());
        $this->assertNull(get_post($id));
        $this->assertSame(404, $this->rest('GET', 'agents/' . $id)->get_status());
    }

    public function test_people_who_cannot_manage_agents_are_refused(): void
    {
        $id = $this->createAgent([]);
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->assertSame(403, $this->rest('GET', 'agents/' . $id)->get_status());
        $this->assertSame(403, $this->rest('PUT', 'agents/' . $id, $this->body())->get_status());
        $this->assertSame(403, $this->rest('GET', 'abilities')->get_status());
        $this->assertSame(403, $this->rest('GET', 'readiness')->get_status());
    }

    public function test_abilities_are_searched_and_paged_on_the_server(): void
    {
        $all = $this->rest('GET', 'abilities', ['per_page' => 100])->get_data();
        $this->assertGreaterThan(3, $all['total']);

        $page = $this->rest('GET', 'abilities', ['per_page' => 2, 'page' => 2])->get_data();
        $this->assertCount(2, $page['items']);
        $this->assertSame((int) ceil($all['total'] / 2), $page['pages']);

        $found = $this->rest('GET', 'abilities', ['search' => 'comment', 'risk' => 'read'])->get_data();
        $this->assertSame(['agent-studio/get-comment'], array_column($found['items'], 'name'));
        $this->assertNotEmpty($found['categories']);
    }

    public function test_triggers_include_plugin_events_and_can_be_searched(): void
    {
        $result = $this->rest('GET', 'triggers', ['search' => 'schedule'])->get_data();

        $this->assertSame(['schedule'], array_column($result['items'], 'key'));
        $this->assertContains('Time', $result['groups']);
    }

    public function test_readiness_says_what_is_missing(): void
    {
        $readiness = $this->rest('GET', 'readiness')->get_data();
        $keys = array_column($readiness['checks'], 'status', 'key');

        $this->assertArrayHasKey('provider', $keys);
        $this->assertArrayHasKey('tool_model', $keys);
        $this->assertIsBool($readiness['ready']);
    }
}
