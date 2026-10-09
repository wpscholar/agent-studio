<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use InvalidArgumentException;
use RuntimeException;
use WP_Post;
use AgentStudio\Contracts\ConnectionRepository;
use AgentStudio\Domain\Connection\AuthMode;
use AgentStudio\Domain\Connection\Connection;
use AgentStudio\Domain\Connection\ConnectionStatus;
use AgentStudio\Domain\Connection\OAuthConfig;
use AgentStudio\Domain\Connection\RemoteTool;

/**
 * Connections are private posts: the title is the name, the post name is the
 * slug, and everything else (never secrets) is JSON in post meta.
 */
final class WordPressConnectionRepository implements ConnectionRepository
{
    public function find(int $id): ?Connection
    {
        return $this->load()[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Connection
    {
        foreach ($this->load() as $connection) {
            if ($connection->slug === $slug) {
                return $connection;
            }
        }

        return null;
    }

    public function all(): array
    {
        return array_values($this->load());
    }

    public function save(Connection $connection): Connection
    {
        $data = [
            'post_type' => PostTypes::CONNECTION,
            'post_status' => 'publish',
            'post_title' => wp_slash($connection->name),
            'post_name' => $connection->slug,
        ];
        if ($connection->id > 0) {
            $data['ID'] = $connection->id;
        }

        $id = wp_insert_post($data, true);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; shown escaped by the caller.
        }

        $meta = [
            'type' => $connection->type,
            'settings' => $connection->settings,
            'auth_mode' => $connection->authMode->value,
            'key_header' => $connection->keyHeader,
            'status' => $connection->status->value,
            'message' => $connection->message,
            'tools' => array_map(static fn (RemoteTool $tool): array => $tool->toArray(), $connection->tools),
            'checked_at' => $connection->checkedAt,
            'oauth' => $connection->oauth?->toArray(),
        ];
        update_post_meta($id, PostTypes::CONNECTION_META, wp_slash((string) wp_json_encode($meta)));

        return $connection->id === $id ? $connection : $connection->with(['id' => $id]);
    }

    public function delete(int $id): void
    {
        if (get_post_type($id) === PostTypes::CONNECTION) {
            wp_delete_post($id, true);
        }
    }

    /**
     * @return array<int, Connection>
     */
    private function load(): array
    {
        $posts = get_posts(
            [
                'post_type' => PostTypes::CONNECTION,
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'suppress_filters' => true,
            ]
        );

        $connections = [];
        foreach ($posts as $post) {
            $connection = $post instanceof WP_Post ? self::hydrate($post) : null;
            if ($connection !== null) {
                $connections[$connection->id] = $connection;
            }
        }

        return $connections;
    }

    private static function hydrate(WP_Post $post): ?Connection
    {
        $raw = get_post_meta($post->ID, PostTypes::CONNECTION_META, true);
        $meta = is_string($raw) ? json_decode($raw, true) : null;
        $meta = is_array($meta) ? $meta : [];

        $settings = [];
        foreach (is_array($meta['settings'] ?? null) ? $meta['settings'] : [] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $settings[$key] = (string) $value;
            }
        }

        $tools = [];
        foreach (is_array($meta['tools'] ?? null) ? $meta['tools'] : [] as $tool) {
            $tool = is_array($tool) ? RemoteTool::fromArray($tool) : null;
            if ($tool !== null) {
                $tools[] = $tool;
            }
        }

        try {
            return new Connection(
                $post->ID,
                is_string($meta['type'] ?? null) ? $meta['type'] : 'mcp',
                $post->post_title,
                $post->post_name,
                $settings,
                AuthMode::fromString(is_string($meta['auth_mode'] ?? null) ? $meta['auth_mode'] : ''),
                is_string($meta['key_header'] ?? null) && $meta['key_header'] !== '' ? $meta['key_header'] : 'Authorization',
                ConnectionStatus::fromString(is_string($meta['status'] ?? null) ? $meta['status'] : ''),
                is_string($meta['message'] ?? null) ? $meta['message'] : '',
                $tools,
                is_int($meta['checked_at'] ?? null) ? $meta['checked_at'] : 0,
                is_array($meta['oauth'] ?? null) ? OAuthConfig::fromArray($meta['oauth']) : null
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
