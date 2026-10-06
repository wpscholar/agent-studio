<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Admin;

use WPAgents\Infrastructure\PostTypes;

/**
 * Loads the admin script and stylesheet on the plugin's own screens only.
 */
final class Assets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || ! in_array($screen->post_type, [PostTypes::AGENT, PostTypes::SKILL], true)) {
            return;
        }

        wp_enqueue_style('wpa-admin', WP_AGENTS_URL . 'assets/admin.css', [], WP_AGENTS_VERSION);
        $script = is_file(WP_AGENTS_PATH . 'build/assets/admin.js') ? 'build/assets/admin.js' : 'assets/admin.js';
        wp_enqueue_script('wpa-admin', WP_AGENTS_URL . $script, [], WP_AGENTS_VERSION, true);
        wp_localize_script(
            'wpa-admin',
            'wpaAdmin',
            [
                'restRoot' => esc_url_raw(rest_url('wp-agents/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'runUrl' => esc_url_raw(admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=wpa-runs&run=')),
                'i18n' => [
                    'running' => __('Starting…', 'wp-agents'),
                    'queued' => __('Queued. Waiting for the run to finish…', 'wp-agents'),
                    'viewRun' => __('View run', 'wp-agents'),
                    'failed' => __('The agent could not be started.', 'wp-agents'),
                    'copied' => __('Copied', 'wp-agents'),
                    'saveFirst' => __('Save the agent before running it.', 'wp-agents'),
                ],
            ]
        );
    }
}
