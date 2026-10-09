<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\Infrastructure\PostTypes;
use AgentStudio\WordPress\Urls;

/**
 * Sends links to the screens Agent Studio used to have (bookmarks, emails
 * about earlier runs) to the screens that replaced them.
 */
final class OldScreens
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'redirect'], 1);
        // Pages that no longer exist are refused before admin_init runs.
        add_action('admin_page_access_denied', [$this, 'redirect']);
    }

    public function redirect(): void
    {
        $target = self::target();
        if ($target !== null && wp_safe_redirect($target)) {
            exit;
        }
    }

    /**
     * Where the current request should go, or null when it is not an old screen.
     */
    public static function target(): ?string
    {
        global $pagenow;

        // Only reading the address to choose a redirect; nothing is changed.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $postType = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $postId = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        // phpcs:enable

        if ($pagenow === 'edit.php' && $postType === PostTypes::AGENT) {
            if ($page === '') {
                return Urls::agents();
            }

            // Pages that used to live under the agent post type: keep the rest of the address (run=, status=…).
            $query = [];
            foreach ($_GET as $key => $value) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (is_string($key) && $key !== 'post_type' && is_scalar($value)) {
                    $query[sanitize_key($key)] = sanitize_text_field(wp_unslash((string) $value));
                }
            }

            return $page === 'agent-studio-templates' ? Urls::newAgent() : add_query_arg($query, admin_url('admin.php'));
        }

        if ($pagenow === 'post-new.php' && $postType === PostTypes::AGENT) {
            return Urls::newAgent();
        }

        if ($pagenow === 'post.php' && $postId > 0 && $action === 'edit' && get_post_type($postId) === PostTypes::AGENT) {
            return Urls::editAgent($postId);
        }

        return null;
    }
}
