<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\PendingSignInStore;

final class InMemoryPendingSignInStore implements PendingSignInStore
{
    /** @var array<string, array<string, mixed>> */
    public array $items = [];

    public function put(string $state, array $data, int $ttlSeconds): void
    {
        $this->items[$state] = $data;
    }

    public function take(string $state): ?array
    {
        $data = $this->items[$state] ?? null;
        unset($this->items[$state]);

        return $data;
    }
}
