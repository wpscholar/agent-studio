<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Run\PendingApproval;
use AgentStudio\Domain\Run\Run;

interface Notifier
{
    /**
     * Tells the people who can approve that a run is waiting.
     */
    public function approvalRequested(Agent $agent, Run $run, PendingApproval $pending): void;
}
