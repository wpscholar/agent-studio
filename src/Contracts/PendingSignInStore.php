<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

/**
 * Remembers a sign-in in progress between sending someone to a service and their return.
 */
interface PendingSignInStore
{
    /**
     * @param array<string, mixed> $data
     */
    public function put(string $state, array $data, int $ttlSeconds): void;

    /**
     * Returns the data once, then forgets it.
     *
     * @return array<string, mixed>|null
     */
    public function take(string $state): ?array;
}
