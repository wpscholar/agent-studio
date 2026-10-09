<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\Infrastructure\PostTypes;

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

        wp_enqueue_style('agent-studio-admin', AGENT_STUDIO_URL . 'assets/admin.css', [], self::version('assets/admin.css'));
        $script = is_file(AGENT_STUDIO_PATH . 'build/assets/admin.js') ? 'build/assets/admin.js' : 'assets/admin.js';
        wp_enqueue_script('agent-studio-admin', AGENT_STUDIO_URL . $script, [], self::version($script), true);
        wp_localize_script(
            'agent-studio-admin',
            'agentStudioAdmin',
            [
                'restRoot' => esc_url_raw(rest_url('agent-studio/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'runUrl' => esc_url_raw(admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=agent-studio-runs&run=')),
                'i18n' => [
                    'running' => __('Starting…', 'agent-studio'),
                    'queued' => __('Queued. Waiting for the run to finish…', 'agent-studio'),
                    'viewRun' => __('View run', 'agent-studio'),
                    'failed' => __('The agent could not be started.', 'agent-studio'),
                    'copied' => __('Copied', 'agent-studio'),
                    'saveFirst' => __('Save the agent before running it.', 'agent-studio'),
                ],
            ]
        );
    }

    /**
     * The plugin version plus the file's modified time, so browsers fetch a
     * changed file even when the plugin version has not changed.
     */
    private static function version(string $file): string
    {
        $mtime = @filemtime(AGENT_STUDIO_PATH . $file); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        return $mtime === false ? AGENT_STUDIO_VERSION : AGENT_STUDIO_VERSION . '.' . $mtime;
    }
}
