<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use InvalidArgumentException;
use RuntimeException;
use WP_Post;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Domain\Channel\Channel;
use AgentStudio\Domain\Channel\ChannelStatus;

/**
 * Channels are private posts: the title is the name, the author is the person
 * who set it up, and everything else (never secrets) is JSON in post meta.
 */
final class WordPressChannelRepository implements ChannelRepository
{
    public function find(int $id): ?Channel
    {
        $post = get_post($id);

        return $post instanceof WP_Post && $post->post_type === PostTypes::CHANNEL ? self::hydrate($post) : null;
    }

    public function all(): array
    {
        $posts = get_posts(
            [
                'post_type' => PostTypes::CHANNEL,
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'suppress_filters' => true,
            ]
        );

        $channels = [];
        foreach ($posts as $post) {
            $channel = $post instanceof WP_Post ? self::hydrate($post) : null;
            if ($channel !== null) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    public function save(Channel $channel): Channel
    {
        $data = [
            'post_type' => PostTypes::CHANNEL,
            'post_status' => 'publish',
            'post_title' => wp_slash($channel->name),
            'post_author' => $channel->ownerId,
        ];
        if ($channel->id > 0) {
            $data['ID'] = $channel->id;
        }

        $id = wp_insert_post($data, true);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; shown escaped by the caller.
        }

        update_post_meta($id, PostTypes::CHANNEL_META, wp_slash((string) wp_json_encode([
            'type' => $channel->type,
            'agent_id' => $channel->agentId,
            'settings' => $channel->settings,
            'status' => $channel->status->value,
            'message' => $channel->message,
            'notify_to' => $channel->notifyTo,
            'notify' => $channel->notify,
        ])));

        return $channel->id === $id ? $channel : $channel->with(['id' => $id]);
    }

    public function delete(int $id): void
    {
        if (get_post_type($id) === PostTypes::CHANNEL) {
            wp_delete_post($id, true);
        }
    }

    private static function hydrate(WP_Post $post): ?Channel
    {
        $raw = get_post_meta($post->ID, PostTypes::CHANNEL_META, true);
        $meta = is_string($raw) ? json_decode($raw, true) : null;
        $meta = is_array($meta) ? $meta : [];

        $settings = [];
        foreach (is_array($meta['settings'] ?? null) ? $meta['settings'] : [] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $settings[$key] = (string) $value;
            }
        }

        try {
            return new Channel(
                $post->ID,
                is_string($meta['type'] ?? null) ? $meta['type'] : '',
                $post->post_title,
                is_int($meta['agent_id'] ?? null) ? $meta['agent_id'] : 0,
                (int) $post->post_author,
                $settings,
                ChannelStatus::fromString(is_string($meta['status'] ?? null) ? $meta['status'] : ''),
                is_string($meta['message'] ?? null) ? $meta['message'] : '',
                is_string($meta['notify_to'] ?? null) ? $meta['notify_to'] : '',
                ($meta['notify'] ?? true) === true
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
