<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\Infrastructure\Schema;
use AgentStudio\WordPress\Capabilities;

final class PluginTest extends IntegrationTestCase
{
    public function test_it_creates_the_runs_table(): void
    {
        global $wpdb;

        $this->assertSame(Schema::table(), $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', Schema::table())));
    }

    public function test_it_registers_agent_and_skill_post_types(): void
    {
        $this->assertTrue(post_type_exists('agent_studio_agent'));
        $this->assertTrue(post_type_exists('agent_studio_skill'));
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
