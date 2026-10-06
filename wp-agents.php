<?php

/**
 * Plugin Name:       WP Agents
 * Plugin URI:        https://github.com/wpscholar/wp-agents
 * Description:       Build AI agents inside WordPress. Trigger them from site events, webhooks or direct calls, and give them your site's abilities and skills.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            Micah Wood
 * Author URI:        https://wpscholar.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-agents
 * Domain Path:       /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('WP_AGENTS_VERSION', '0.1.0');
define('WP_AGENTS_FILE', __FILE__);
define('WP_AGENTS_PATH', plugin_dir_path(__FILE__));
define('WP_AGENTS_URL', plugin_dir_url(__FILE__));

if (version_compare(PHP_VERSION, '8.2', '<')) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('WP Agents requires PHP 8.2 or later.', 'wp-agents')
                . '</p></div>';
        }
    );

    return;
}

if (! file_exists(__DIR__ . '/vendor/autoload.php')) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('WP Agents is missing its dependencies. Run "composer install" in the plugin folder, or install a release zip.', 'wp-agents')
                . '</p></div>';
        }
    );

    return;
}

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, [WPAgents\WordPress\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [WPAgents\WordPress\Plugin::class, 'deactivate']);

add_action('plugins_loaded', [WPAgents\WordPress\Plugin::class, 'boot']);
