<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Hooks;

use Throwable;
use WP_Comment;
use WP_Post;
use AgentStudio\Application\Dispatch\DispatchTriggerHandler;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Infrastructure\PostTypes;

/**
 * Turns WordPress events into trigger events. Contains no agent logic: it only
 * describes what happened and hands it to the dispatcher.
 */
final class TriggerHooks
{
    private const MAX_COMMENT_CHARS = 2000;

    public function __construct(private readonly DispatchTriggerHandler $dispatch)
    {
    }

    public function register(): void
    {
        add_action('transition_post_status', [$this, 'onPostStatus'], 10, 3);
        add_action('user_register', [$this, 'onUserRegistered'], 10, 1);
        add_action('wp_insert_comment', [$this, 'onComment'], 10, 2);
    }

    public function onPostStatus(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        if ($newStatus === $oldStatus || $newStatus === 'auto-draft' || $this->isIgnoredPostType($post->post_type)) {
            return;
        }

        $this->dispatch(
            TriggerType::PostStatus,
            [
                'post_id' => $post->ID,
                'post_type' => $post->post_type,
                'new_status' => $newStatus,
                'old_status' => $oldStatus,
                'title' => $post->post_title,
                'author_id' => (int) $post->post_author,
            ],
            sprintf('post:%d:%s->%s', $post->ID, $oldStatus, $newStatus)
        );
    }

    public function onUserRegistered(int $userId): void
    {
        $user = get_userdata($userId);
        if ($user === false) {
            return;
        }

        $this->dispatch(
            TriggerType::UserRegistered,
            [
                'user_id' => $user->ID,
                'login' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => array_values($user->roles),
            ],
            'user:' . $user->ID
        );
    }

    public function onComment(int $commentId, WP_Comment $comment): void
    {
        /**
         * Filters which comment types start agents. Pingbacks and trackbacks are left out by default.
         *
         * @param string[] $types Default array( 'comment' ).
         */
        $types = (array) apply_filters('agent_studio_comment_trigger_types', ['comment']);
        if (! in_array($comment->comment_type, $types, true)) {
            return;
        }

        $post = get_post((int) $comment->comment_post_ID);

        $this->dispatch(
            TriggerType::CommentPosted,
            [
                'comment_id' => $commentId,
                'post_id' => (int) $comment->comment_post_ID,
                'post_type' => $post instanceof WP_Post ? $post->post_type : '',
                'status' => $this->commentStatus((string) $comment->comment_approved),
                'author_name' => $comment->comment_author,
                'author_email' => $comment->comment_author_email,
                'content' => mb_substr($comment->comment_content, 0, self::MAX_COMMENT_CHARS),
                'parent_id' => (int) $comment->comment_parent,
            ],
            'comment:' . $commentId
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function dispatch(TriggerType $type, array $payload, string $dedupeKey): void
    {
        /**
         * Filters the event data given to agents. Use it to remove personal data before it reaches the model.
         *
         * @param array<string, mixed> $payload The event data.
         * @param string               $type    The trigger type, for example "user_registered".
         */
        $filtered = apply_filters('agent_studio_trigger_payload', $payload, $type->value);

        try {
            $this->dispatch->handle(
                new TriggerEvent($type, is_array($filtered) ? $filtered : $payload, $dedupeKey)
            );
        } catch (Throwable $e) {
            // Never let an agent problem break the action that triggered it (saving a post, signing up).
            do_action('agent_studio_dispatch_failed', $e, $type->value);

            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Agent Studio: could not dispatch ' . $type->value . ': ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }
    }

    private function isIgnoredPostType(string $postType): bool
    {
        /**
         * Filters post types that never start agents.
         *
         * @param string[] $postTypes Internal WordPress types and the plugin's own.
         */
        $ignored = (array) apply_filters(
            'agent_studio_ignored_post_types',
            ['revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request', PostTypes::AGENT, PostTypes::KNOWLEDGE, PostTypes::CONNECTION]
        );

        return in_array($postType, $ignored, true);
    }

    private function commentStatus(string $approved): string
    {
        return match ($approved) {
            '1' => 'approved',
            '0' => 'hold',
            default => $approved,
        };
    }
}
