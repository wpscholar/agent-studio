<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\Infrastructure\Schema;
use AgentStudio\Infrastructure\WordPressAgentRepository;
use AgentStudio\Infrastructure\WordPressSkillRepository;
use AgentStudio\WordPress\Plugin;
use AgentStudio\WordPress\PostTypeRegistrar;
use AgentStudio\WordPress\Capabilities;

final class PluginTest extends IntegrationTestCase
{
    public function test_it_creates_the_runs_table(): void
    {
        global $wpdb;

        $this->assertSame(Schema::table(), $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', Schema::table())));
    }

    public function test_it_registers_the_agent_and_knowledge_types(): void
    {
        $this->assertTrue(post_type_exists('agent_studio_agent'));
        $this->assertTrue(post_type_exists('agent_studio_kb'));
        $this->assertTrue(taxonomy_exists('agent_studio_kb_type'));
        $this->assertFalse(post_type_exists('agent_studio_skill'));
    }

    public function test_skills_from_the_old_post_type_move_into_knowledge_with_their_history(): void
    {
        global $wpdb;

        $id = self::factory()->post->create(['post_type' => 'post', 'post_name' => 'tone', 'post_title' => 'Tone', 'post_excerpt' => 'Use for tone.', 'post_content' => 'Be warm.']);
        $wpdb->update($wpdb->posts, ['post_type' => 'agent_studio_skill'], ['ID' => $id]);
        clean_post_cache($id);
        delete_option('agent_studio_content_version');

        Plugin::container()->get(PostTypeRegistrar::class)->migrate();

        $skill = (new WordPressSkillRepository())->findBySlug('tone');
        $this->assertNotNull($skill);
        $this->assertSame('Use for tone.', $skill->description);
        $this->assertSame('agent_studio_kb', get_post_type($id));
    }

    public function test_agents_saved_before_statuses_were_post_statuses_keep_their_status(): void
    {
        $paused = wp_insert_post(['post_type' => 'agent_studio_agent', 'post_status' => 'publish', 'post_title' => 'Old paused']);
        $active = wp_insert_post(['post_type' => 'agent_studio_agent', 'post_status' => 'publish', 'post_title' => 'Old active']);
        update_post_meta($paused, '_agent_studio_config', wp_slash((string) wp_json_encode(['status' => 'paused'])));
        update_post_meta($active, '_agent_studio_config', wp_slash((string) wp_json_encode(['status' => 'active'])));
        update_option('agent_studio_content_version', '2');

        Plugin::container()->get(PostTypeRegistrar::class)->migrate();

        $this->assertSame('agent_studio_paused', get_post_status($paused));
        $this->assertSame('publish', get_post_status($active));
        $this->assertSame('3', get_option('agent_studio_content_version'));
    }

    public function test_administrators_have_every_agent_studio_capability(): void
    {
        foreach (Capabilities::ALL as $capability) {
            $this->assertTrue(current_user_can($capability), $capability);
        }
    }

    public function test_capabilities_can_be_given_to_other_roles_one_by_one(): void
    {
        $editor = self::factory()->user->create(['role' => 'editor']);
        get_role('editor')->add_cap(Capabilities::APPROVE);

        try {
            $this->assertTrue(user_can($editor, Capabilities::APPROVE));
            $this->assertFalse(user_can($editor, Capabilities::MANAGE));
            $this->assertTrue(Capabilities::canApprove(null, $editor));
        } finally {
            get_role('editor')->remove_cap(Capabilities::APPROVE);
        }
    }

    public function test_a_role_can_deny_one_capability_to_administrators(): void
    {
        get_role('administrator')->add_cap(Capabilities::APPROVE, false);

        try {
            $user = self::factory()->user->create(['role' => 'administrator']);
            $this->assertFalse(user_can($user, Capabilities::APPROVE));
            $this->assertTrue(user_can($user, Capabilities::MANAGE));
        } finally {
            get_role('administrator')->remove_cap(Capabilities::APPROVE);
        }
    }

    public function test_the_person_who_set_up_an_agent_can_approve_its_actions(): void
    {
        $manager = self::factory()->user->create(['role' => 'editor']);
        get_role('editor')->add_cap(Capabilities::MANAGE);

        try {
            $agentId = wp_insert_post(['post_type' => 'agent_studio_agent', 'post_status' => 'publish', 'post_title' => 'Mine', 'post_author' => $manager]);
            $other = wp_insert_post(['post_type' => 'agent_studio_agent', 'post_status' => 'publish', 'post_title' => 'Theirs', 'post_author' => $this->adminId]);
            $repository = new WordPressAgentRepository();

            $this->assertTrue(Capabilities::canApprove($repository->find($agentId), $manager));
            $this->assertFalse(Capabilities::canApprove($repository->find($other), $manager));
        } finally {
            get_role('editor')->remove_cap(Capabilities::MANAGE);
        }
    }

    public function test_administrators_can_manage_agents(): void
    {
        $this->assertTrue(Capabilities::canManage());
        $this->assertTrue(current_user_can('manage_options'));
    }

    public function test_other_users_cannot_manage_agents(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->assertFalse(Capabilities::canManage());
    }

    /**
     * Mapping the post type's meta capabilities onto manage_options once made
     * manage_options itself behave like a per-post capability for the whole site.
     */
    public function test_registering_the_post_types_does_not_change_how_manage_options_works(): void
    {
        global $post_type_meta_caps;

        $this->assertTrue(! isset($post_type_meta_caps['manage_options']));
    }

    public function test_the_prune_job_is_scheduled(): void
    {
        $this->assertTrue((bool) wp_next_scheduled('agent_studio_prune_runs'));
    }
}
