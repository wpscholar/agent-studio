<?php

/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes the run log and plugin options. Agents and skills are your content,
 * so they are kept unless WP_AGENTS_REMOVE_ALL_DATA is defined as true.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpa_runs');

delete_option('wpa_db_version');
delete_option('wpa_settings');

wp_clear_scheduled_hook('wpa_prune_runs');

if (defined('WP_AGENTS_REMOVE_ALL_DATA') && WP_AGENTS_REMOVE_ALL_DATA === true) {
    $posts = get_posts(
        [
            'post_type' => ['wpa_agent', 'wpa_skill'],
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
        ]
    );

    foreach ($posts as $post_id) {
        wp_delete_post((int) $post_id, true);
    }
}
