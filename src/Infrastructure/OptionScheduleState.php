<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Contracts\ScheduleState;

/**
 * Keeps schedule state in one option: agent ID => [timetable signature, last slot].
 */
final class OptionScheduleState implements ScheduleState
{
    public const OPTION = 'agent_studio_schedule_state';

    public function lastSlot(int $agentId, string $signature): ?int
    {
        $entry = $this->all()[$agentId] ?? null;

        return is_array($entry) && ($entry['signature'] ?? null) === $signature && is_int($entry['slot'] ?? null)
            ? $entry['slot']
            : null;
    }

    public function remember(int $agentId, string $signature, int $slot): void
    {
        $state = $this->all();
        $state[$agentId] = ['signature' => $signature, 'slot' => $slot];
        update_option(self::OPTION, $state, false);
    }

    public function keepOnly(array $agentIds): void
    {
        $state = $this->all();
        $kept = array_intersect_key($state, array_flip($agentIds));
        if (count($kept) !== count($state)) {
            update_option(self::OPTION, $kept, false);
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function all(): array
    {
        $state = get_option(self::OPTION, []);

        return is_array($state) ? $state : [];
    }
}
