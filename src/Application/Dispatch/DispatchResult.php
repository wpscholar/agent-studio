<?php

declare(strict_types=1);

namespace AgentStudio\Application\Dispatch;

final class DispatchResult
{
    /**
     * @param list<AgentDispatch> $dispatches
     * @param bool                $suppressed True when the event was ignored because an agent caused it.
     */
    public function __construct(
        public readonly array $dispatches = [],
        public readonly bool $suppressed = false,
    ) {
    }

    public static function suppressed(): self
    {
        return new self([], true);
    }

    /**
     * @return list<int>
     */
    public function queuedRunIds(): array
    {
        $ids = [];
        foreach ($this->dispatches as $dispatch) {
            if ($dispatch->outcome === DispatchOutcome::Queued && $dispatch->runId !== null) {
                $ids[] = $dispatch->runId;
            }
        }

        return $ids;
    }

    public function forAgent(int $agentId): ?AgentDispatch
    {
        foreach ($this->dispatches as $dispatch) {
            if ($dispatch->agentId === $agentId) {
                return $dispatch;
            }
        }

        return null;
    }
}
