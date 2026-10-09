<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\Infrastructure\PostTypes;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Urls;

/**
 * Loads the admin app and stylesheet on the plugin's own screens only.
 *
 * The app is built from assets/src with `npm run build` and uses the copies of
 * React and the WordPress components that WordPress already ships.
 */
final class Assets
{
    private const SCRIPT = 'build/assets/admin.js';

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || ! self::isOurs($screen->id, (string) $screen->post_type)) {
            return;
        }

        wp_enqueue_style('agent-studio-admin', AGENT_STUDIO_URL . 'assets/admin.css', ['wp-components'], self::version('assets/admin.css'));

        if (! is_file(AGENT_STUDIO_PATH . self::SCRIPT)) {
            add_action('admin_notices', [self::class, 'missingBuild']);

            return;
        }

        wp_enqueue_script(
            'agent-studio-admin',
            AGENT_STUDIO_URL . self::SCRIPT,
            ['wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch'],
            self::version(self::SCRIPT),
            true
        );
        wp_set_script_translations('agent-studio-admin', 'agent-studio');
        wp_localize_script(
            'agent-studio-admin',
            'agentStudioAdmin',
            [
                'restRoot' => esc_url_raw(rest_url('agent-studio/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'urls' => [
                    'agents' => esc_url_raw(Urls::agents()),
                    'newAgent' => esc_url_raw(Urls::newAgent()),
                    'skills' => esc_url_raw(Urls::skills()),
                    'connections' => esc_url_raw(Urls::connections()),
                    'channels' => esc_url_raw(Urls::channels()),
                    'abilities' => esc_url_raw(Urls::abilities()),
                    'runs' => esc_url_raw(Urls::runs()),
                    'connectors' => esc_url_raw(admin_url('options-connectors.php')),
                    'timezone' => esc_url_raw(admin_url('options-general.php')),
                ],
                'can' => [
                    'manage' => Capabilities::canManage(),
                ],
                'userId' => get_current_user_id(),
                'timezone' => self::timezone(),
            ]
        );
    }

    public static function missingBuild(): void
    {
        echo '<div class="notice notice-error"><p>' . esc_html__('Agent Studio’s admin screens have not been built. Run “npm run build” in the plugin folder.', 'agent-studio') . '</p></div>';
    }

    private static function isOurs(string $screenId, string $postType): bool
    {
        return $postType === PostTypes::AGENT || str_contains($screenId, 'agent-studio');
    }

    /**
     * The site's timezone for people: "Europe/London", or "UTC+02:00" for sites set to a plain offset.
     */
    private static function timezone(): string
    {
        $zone = wp_timezone_string();

        return preg_match('/^[+-]/', $zone) === 1 ? 'UTC' . $zone : $zone;
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
