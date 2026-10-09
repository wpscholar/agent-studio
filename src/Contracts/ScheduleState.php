<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

/**
 * Remembers the last schedule slot handled for each agent, so each slot
 * starts an agent once.
 */
interface ScheduleState
{
    /**
     * The last slot handled for this agent under this timetable, or null if
     * the agent has not been seen with it.
     */
    public function lastSlot(int $agentId, string $signature): ?int;

    public function remember(int $agentId, string $signature, int $slot): void;

    /**
     * Forgets every agent not in the list.
     *
     * @param list<int> $agentIds
     */
    public function keepOnly(array $agentIds): void;
}
