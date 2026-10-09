<?php

declare(strict_types=1);

namespace AgentStudio\Application\Connections;

use InvalidArgumentException;
use AgentStudio\Contracts\Connection\ConnectionType;

/**
 * The kinds of connection available on this site.
 */
final class ConnectionTypes
{
    /** @var array<string, ConnectionType> */
    private array $types = [];

    public function register(ConnectionType $type): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $type->key()) !== 1) {
            throw new InvalidArgumentException('A connection type key uses lowercase letters, numbers, hyphens and underscores.');
        }

        $this->types[$type->key()] = $type;
    }

    public function get(string $key): ?ConnectionType
    {
        return $this->types[$key] ?? null;
    }

    /**
     * @return list<ConnectionType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }
}
