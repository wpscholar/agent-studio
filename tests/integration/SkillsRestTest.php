<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class SkillsRestTest extends IntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');
    }

    public function tear_down(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;

        parent::tear_down();
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
     * @return array<string, mixed>
     */
    private function create(string $name = 'Release notes writer'): array
    {
        $response = $this->call('POST', 'skills', [
            'name' => $name,
            'description' => 'Use when writing release notes.',
            'instructions' => 'Group changes by type.',
        ]);
        $this->assertSame(201, $response->get_status());

        return $response->get_data();
    }

    public function test_creating_a_skill_gives_it_an_id_from_its_name(): void
    {
        $skill = $this->create();

        $this->assertSame('release-notes-writer', $skill['slug']);
        $this->assertSame('Group changes by type.', $skill['instructions']);
        $this->assertSame('Use when writing release notes.', $this->call('GET', 'skills')->get_data()[0]['description']);
    }

    public function test_a_second_skill_with_the_same_name_is_refused(): void
    {
        $this->create();

        $response = $this->call('POST', 'skills', ['name' => 'Release Notes Writer', 'description' => 'x', 'instructions' => 'y']);

        $this->assertSame(409, $response->get_status());
    }

    public function test_missing_fields_get_plain_explanations(): void
    {
        $response = $this->call('POST', 'skills', ['name' => 'Tone', 'description' => '', 'instructions' => 'Be warm.']);

        $this->assertSame(400, $response->get_status());
        $this->assertStringContainsString('when an agent should use', $response->get_data()['message']);
    }

    public function test_renaming_keeps_the_id_agents_refer_to(): void
    {
        $this->create();

        $response = $this->call('PUT', 'skills/release-notes-writer', ['name' => 'Changelog writer', 'description' => 'Use for changelogs.', 'instructions' => 'Be brief.']);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('release-notes-writer', $response->get_data()['slug']);
        $this->assertSame('Changelog writer', $response->get_data()['name']);
    }

    public function test_the_list_shows_which_agents_use_each_skill(): void
    {
        $this->create();
        $agentId = $this->createAgent(['skills' => ['release-notes-writer']], 'Notes agent');

        $usedBy = $this->call('GET', 'skills')->get_data()[0]['usedBy'];

        $this->assertSame($agentId, $usedBy[0]['id']);
        $this->assertSame('Notes agent', $usedBy[0]['name']);
    }

    public function test_an_earlier_version_can_be_restored(): void
    {
        $this->create();
        $this->call('PUT', 'skills/release-notes-writer', ['name' => 'Release notes writer', 'description' => 'Use when writing release notes.', 'instructions' => 'Changed.']);

        $revisions = $this->call('GET', 'skills/release-notes-writer')->get_data()['revisions'];
        $earlier = array_values(array_filter($revisions, static fn (array $r): bool => ! $r['current']));
        $this->assertNotEmpty($earlier);
        $oldest = end($earlier);

        $this->assertSame('Group changes by type.', $this->call('GET', 'skills/release-notes-writer/revisions/' . $oldest['id'])->get_data()['instructions']);

        $restored = $this->call('POST', 'skills/release-notes-writer/revisions/' . $oldest['id'] . '/restore');

        $this->assertSame('Group changes by type.', $restored->get_data()['instructions']);
    }

    public function test_a_revision_of_another_skill_cannot_be_restored(): void
    {
        $this->create();
        $this->create('Tone');
        $this->call('PUT', 'skills/tone', ['name' => 'Tone', 'description' => 'd', 'instructions' => 'Changed.']);
        $toneRevision = $this->call('GET', 'skills/tone')->get_data()['revisions'][0]['id'];

        $response = $this->call('POST', 'skills/release-notes-writer/revisions/' . $toneRevision . '/restore');

        $this->assertSame(404, $response->get_status());
    }

    public function test_import_previews_then_saves(): void
    {
        $markdown = "---\nname: \"Support: refunds\"\ndescription: Use for refund requests.\n---\n\nBe kind.";

        $preview = $this->call('POST', 'skills/import', ['markdown' => $markdown, 'dry_run' => true])->get_data();
        $this->assertFalse($preview['exists']);
        $this->assertSame([], $this->call('GET', 'skills')->get_data());

        $this->call('POST', 'skills/import', ['markdown' => $markdown]);

        $this->assertSame('Support: refunds', $this->call('GET', 'skills/support-refunds')->get_data()['name']);
        $this->assertTrue($this->call('POST', 'skills/import', ['markdown' => $markdown, 'dry_run' => true])->get_data()['exists']);
    }

    public function test_export_reads_back_as_the_same_skill(): void
    {
        $this->call('POST', 'skills', ['name' => 'Quotes "and" colons: ok', 'description' => 'Use when: always.', 'instructions' => "Line one.\n\nLine two."]);

        $file = $this->call('GET', 'skills/quotes-and-colons-ok/export')->get_data();
        $this->call('DELETE', 'skills/quotes-and-colons-ok');
        $this->call('POST', 'skills/import', ['markdown' => $file['markdown']]);

        $skill = $this->call('GET', 'skills/quotes-and-colons-ok')->get_data();
        $this->assertSame('Quotes "and" colons: ok', $skill['name']);
        $this->assertSame('Use when: always.', $skill['description']);
        $this->assertSame("Line one.\n\nLine two.", $skill['instructions']);
    }

    public function test_deleting_removes_the_skill(): void
    {
        $this->create();

        $this->assertSame(200, $this->call('DELETE', 'skills/release-notes-writer')->get_status());
        $this->assertSame(404, $this->call('GET', 'skills/release-notes-writer')->get_status());
    }

    public function test_people_who_cannot_manage_agents_are_refused(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->assertSame(403, $this->call('GET', 'skills')->get_status());
        $this->assertSame(403, $this->call('POST', 'skills', ['name' => 'x', 'description' => 'y', 'instructions' => 'z'])->get_status());
    }
}
