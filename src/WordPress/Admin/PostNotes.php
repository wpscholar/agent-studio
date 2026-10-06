<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Admin;

use WP_Post;
use WPAgents\Infrastructure\PostTypes;

/**
 * Shows the private notes agents left on a post, in the post editor.
 */
final class PostNotes
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox'], 10, 2);
    }

    public function addMetaBox(string $postType, mixed $post): void
    {
        if (! $post instanceof WP_Post || in_array($postType, [PostTypes::AGENT, PostTypes::SKILL], true)) {
            return;
        }

        $notes = get_post_meta($post->ID, PostTypes::NOTES_META, true);
        if (! is_array($notes) || $notes === []) {
            return;
        }

        add_meta_box('wpa-notes', __('Agent notes', 'wp-agents'), [$this, 'render'], $postType, 'side', 'default');
    }

    public function render(WP_Post $post): void
    {
        $notes = get_post_meta($post->ID, PostTypes::NOTES_META, true);
        $notes = is_array($notes) ? array_reverse($notes) : [];

        echo '<p class="description">' . esc_html__('Private notes left by agents. Visitors never see these.', 'wp-agents') . '</p>';

        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }

            printf(
                '<div class="wpa-note"><div class="wpa-note__meta">%1$s · %2$s</div><div>%3$s</div></div>',
                esc_html((string) ($note['author'] ?? '')),
                esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) ($note['at'] ?? 0))),
                nl2br(esc_html((string) ($note['note'] ?? '')))
            );
        }
    }
}
