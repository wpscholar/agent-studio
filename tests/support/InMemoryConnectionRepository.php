<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\ConnectionRepository;
use AgentStudio\Domain\Connection\Connection;

final class InMemoryConnectionRepository implements ConnectionRepository
{
    /** @var array<int, Connection> */
    public array $items = [];

    private int $nextId = 1;

    public function find(int $id): ?Connection
    {
        return $this->items[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Connection
    {
        foreach ($this->items as $connection) {
            if ($connection->slug === $slug) {
                return $connection;
            }
        }

        return null;
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function save(Connection $connection): Connection
    {
        if ($connection->id === 0) {
            $connection = $connection->with(['id' => $this->nextId++]);
        }

        return $this->items[$connection->id] = $connection;
    }

    public function delete(int $id): void
    {
        unset($this->items[$id]);
    }
}
