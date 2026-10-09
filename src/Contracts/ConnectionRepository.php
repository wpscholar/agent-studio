<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Domain\Connection\Connection;

interface ConnectionRepository
{
    public function find(int $id): ?Connection;

    public function findBySlug(string $slug): ?Connection;

    /**
     * @return list<Connection>
     */
    public function all(): array;

    /**
     * Creates the connection when its id is 0, otherwise updates it. Returns it with its id.
     */
    public function save(Connection $connection): Connection;

    public function delete(int $id): void;
}
