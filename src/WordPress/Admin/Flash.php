<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

/**
 * One-time admin notices that survive a redirect, stored per user.
 */
final class Flash
{
    public static function add(string $type, string $message): void
    {
        set_transient(self::key(), ['type' => $type === 'error' ? 'error' : 'success', 'message' => $message], MINUTE_IN_SECONDS);
    }

    public static function redirect(string $url, string $type, string $message): never
    {
        self::add($type, $message);
        wp_safe_redirect($url);
        exit;
    }

    public static function render(): void
    {
        $flash = get_transient(self::key());
        if (! is_array($flash)) {
            return;
        }

        delete_transient(self::key());
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($flash['type'] === 'error' ? 'error' : 'success'),
            esc_html((string) $flash['message'])
        );
    }

    private static function key(): string
    {
        return 'agent_studio_flash_' . get_current_user_id();
    }
}
