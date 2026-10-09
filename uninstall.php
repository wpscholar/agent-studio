<?php

/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes the run log and plugin options. Agents and skills are your content,
 * so they are kept unless AGENT_STUDIO_REMOVE_ALL_DATA is defined as true.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'agent_studio_runs');

delete_option('agent_studio_db_version');
delete_option('agent_studio_settings');
delete_option('agent_studio_schedule_state');
delete_option('agent_studio_content_version');

wp_clear_scheduled_hook('agent_studio_prune_runs');
wp_clear_scheduled_hook('agent_studio_check_schedules');

if (defined('AGENT_STUDIO_REMOVE_ALL_DATA') && AGENT_STUDIO_REMOVE_ALL_DATA === true) {
    $posts = get_posts(
        [
            'post_type' => ['agent_studio_agent', 'agent_studio_kb', 'agent_studio_skill'],
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
        ]
    );

    foreach ($posts as $post_id) {
        wp_delete_post((int) $post_id, true);
    }

    // The knowledge type terms (skill, and later memory and note). The taxonomy is not registered here.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery
    $rows = $wpdb->get_results($wpdb->prepare("SELECT term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'agent_studio_kb_type'));
    foreach ($rows as $row) {
        $wpdb->delete($wpdb->term_relationships, ['term_taxonomy_id' => (int) $row->term_taxonomy_id]);
        $wpdb->delete($wpdb->term_taxonomy, ['term_taxonomy_id' => (int) $row->term_taxonomy_id]);
        $wpdb->delete($wpdb->terms, ['term_id' => (int) $row->term_id]);
    }
    // phpcs:enable
}
