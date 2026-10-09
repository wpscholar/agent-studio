<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\WordPress\Capabilities;

/**
 * A screen drawn by the admin app (assets/src). PHP prints a container and
 * the app fills it in, talking to the plugin's REST API.
 */
final class AppScreen
{
    /**
     * @param string $screen Which app screen to show, for example "skills".
     * @param string $title  Page title, shown until the app loads.
     */
    public function __construct(
        public readonly string $screen,
        private readonly string $title,
    ) {
    }

    public function render(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to see this page.', 'agent-studio'), '', ['response' => 403]);
        }

        printf(
            // WordPress moves admin notices after .wp-header-end; without it they would land inside the app and vanish.
            '<div class="wrap agent-studio-app-wrap"><hr class="wp-header-end agent-studio-header-end"><div id="agent-studio-app" data-screen="%1$s"><h1>%2$s</h1><p class="agent-studio-app-loading">%3$s</p><noscript><p>%4$s</p></noscript></div></div>',
            esc_attr($this->screen),
            esc_html($this->title),
            esc_html__('Loading…', 'agent-studio'),
            esc_html__('This screen needs JavaScript.', 'agent-studio')
        );
    }
}
