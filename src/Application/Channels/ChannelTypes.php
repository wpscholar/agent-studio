<?php

declare(strict_types=1);

namespace AgentStudio\Application\Channels;

use InvalidArgumentException;
use AgentStudio\Contracts\Channel\ChannelType;

/**
 * The kinds of channel available on this site.
 */
final class ChannelTypes
{
    /** @var array<string, ChannelType> */
    private array $types = [];

    public function register(ChannelType $type): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $type->key()) !== 1) {
            throw new InvalidArgumentException('A channel type key uses lowercase letters, numbers, hyphens and underscores.');
        }

        $this->types[$type->key()] = $type;
    }

    public function get(string $key): ?ChannelType
    {
        return $this->types[$key] ?? null;
    }

    /**
     * @return list<ChannelType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }
}
