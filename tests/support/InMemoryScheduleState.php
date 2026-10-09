<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\ScheduleState;

final class InMemoryScheduleState implements ScheduleState
{
    /** @var array<int, array{signature: string, slot: int}> */
    public array $state = [];

    public function lastSlot(int $agentId, string $signature): ?int
    {
        $entry = $this->state[$agentId] ?? null;

        return $entry !== null && $entry['signature'] === $signature ? $entry['slot'] : null;
    }

    public function remember(int $agentId, string $signature, int $slot): void
    {
        $this->state[$agentId] = ['signature' => $signature, 'slot' => $slot];
    }

    public function keepOnly(array $agentIds): void
    {
        $this->state = array_intersect_key($this->state, array_flip($agentIds));
    }
}
