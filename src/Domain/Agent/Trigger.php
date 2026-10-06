<?php

declare(strict_types=1);

namespace WPAgents\Domain\Agent;

/**
 * One way an agent can be started, with optional filters.
 *
 * Supported config keys:
 *  - post_status:    post_types, from, to   (lists; empty means any)
 *  - user_registered: roles                 (list; empty means any)
 *  - comment_posted:  post_types            (list; empty means any)
 *  - webhook, manual: none
 */
final class Trigger
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly TriggerType $type,
        public readonly array $config = [],
    ) {
    }

    public function matches(TriggerEvent $event): bool
    {
        if ($event->type !== $this->type) {
            return false;
        }

        $payload = $event->payload;

        return match ($this->type) {
            TriggerType::PostStatus => $this->listAllows('post_types', self::string($payload['post_type'] ?? ''))
                && $this->listAllows('to', self::string($payload['new_status'] ?? ''))
                && $this->listAllows('from', self::string($payload['old_status'] ?? '')),
            TriggerType::UserRegistered => $this->listAllowsAny('roles', self::strings($payload['roles'] ?? [])),
            TriggerType::CommentPosted => $this->listAllows('post_types', self::string($payload['post_type'] ?? '')),
            TriggerType::Webhook, TriggerType::Manual => true,
        };
    }

    /**
     * @return array{type: string, config: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['type' => $this->type->value, 'config' => $this->config];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $type = TriggerType::tryFrom(self::string($data['type'] ?? ''));
        if ($type === null) {
            return null;
        }

        $config = $data['config'] ?? [];

        return new self($type, is_array($config) ? $config : []);
    }

    /**
     * @return list<string>
     */
    public function list(string $key): array
    {
        $raw = $this->config[$key] ?? [];
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        $items = [];
        foreach ((array) $raw as $item) {
            $item = trim(self::string($item));
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function listAllows(string $key, string $value): bool
    {
        $list = $this->list($key);

        return $list === [] || in_array($value, $list, true);
    }

    /**
     * @param list<string> $values
     */
    private function listAllowsAny(string $key, array $values): bool
    {
        $list = $this->list($key);

        return $list === [] || array_intersect($list, $values) !== [];
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($v): string => self::string($v), $value));
    }
}
