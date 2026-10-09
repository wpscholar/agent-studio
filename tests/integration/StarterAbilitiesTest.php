<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use WP_Error;

final class StarterAbilitiesTest extends IntegrationTestCase
{
    /**
     * @param array<string, mixed>|null $input
     */
    private function run_ability(string $name, ?array $input = null): mixed
    {
        $ability = wp_get_ability($name);
        $this->assertNotNull($ability, $name . ' is registered');

        return $ability->execute($input);
    }

    public function test_get_post_returns_the_post(): void
    {
        $id = self::factory()->post->create(['post_title' => 'Hello', 'post_content' => 'World']);

        $result = $this->run_ability('agent-studio/get-post', ['id' => $id]);

        $this->assertSame('Hello', $result['title']);
        $this->assertSame('World', $result['content']);
    }

    public function test_get_post_respects_the_acting_users_permissions(): void
    {
        $id = self::factory()->post->create(['post_status' => 'private']);
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        $result = $this->run_ability('agent-studio/get-post', ['id' => $id]);

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_update_post_changes_only_the_fields_sent(): void
    {
        $id = self::factory()->post->create(['post_title' => 'Old', 'post_content' => 'Keep me']);

        $this->run_ability('agent-studio/update-post', ['id' => $id, 'title' => 'New']);

        $this->assertSame('New', get_post($id)->post_title);
        $this->assertSame('Keep me', get_post($id)->post_content);
    }

    public function test_update_post_rejects_unknown_statuses(): void
    {
        $id = self::factory()->post->create();

        $result = $this->run_ability('agent-studio/update-post', ['id' => $id, 'status' => 'trash']);

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_add_post_note_stores_a_private_note(): void
    {
        $id = self::factory()->post->create();

        $result = $this->run_ability('agent-studio/add-post-note', ['post_id' => $id, 'note' => '<b>Hi</b>']);

        $this->assertSame(1, $result['notes']);
        $notes = get_post_meta($id, '_agent_studio_notes', true);
        $this->assertSame('Hi', $notes[0]['note']);
    }

    public function test_set_comment_status_holds_a_comment(): void
    {
        $postId = self::factory()->post->create();
        $commentId = self::factory()->comment->create(['comment_post_ID' => $postId, 'comment_approved' => '1']);

        $this->run_ability('agent-studio/set-comment-status', ['id' => $commentId, 'status' => 'hold']);

        $this->assertSame('unapproved', wp_get_comment_status($commentId));
    }

    public function test_send_email_is_blocked_by_the_recipient_filter(): void
    {
        add_filter('agent_studio_allow_email_recipient', '__return_false');

        $result = $this->run_ability('agent-studio/send-email', ['to' => 'a@example.com', 'subject' => 'Hi', 'body' => 'There']);

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_send_email_is_classified_as_external(): void
    {
        $risks = [];
        foreach ((new \AgentStudio\Infrastructure\AbilitiesApiGateway())->available() as $info) {
            $risks[$info->name] = $info->risk->value;
        }

        $this->assertSame('external', $risks['agent-studio/send-email']);
        $this->assertSame('read', $risks['agent-studio/get-post']);
        $this->assertSame('write', $risks['agent-studio/update-post']);
    }

    public function test_the_run_agent_ability_is_never_offered_to_agents(): void
    {
        $names = array_map(static fn ($info): string => $info->name, (new \AgentStudio\Infrastructure\AbilitiesApiGateway())->available());

        $this->assertFalse(in_array('agent-studio/run-agent', $names, true));
    }

    public function test_management_abilities_need_the_manage_capability(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->assertInstanceOf(WP_Error::class, $this->run_ability('agent-studio/list-agents'));
    }
}
