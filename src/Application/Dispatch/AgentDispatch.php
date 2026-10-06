<?php

declare(strict_types=1);

namespace WPAgents\Application\Dispatch;

final class AgentDispatch
{
    public function __construct(
        public readonly int $agentId,
        public readonly DispatchOutcome $outcome,
        public readonly ?int $runId = null,
    ) {
    }
}
