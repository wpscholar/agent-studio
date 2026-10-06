<?php

declare(strict_types=1);

namespace WPAgents\Contracts;

use WPAgents\Domain\Agent\Agent;
use WPAgents\Domain\Run\PendingApproval;
use WPAgents\Domain\Run\Run;

interface Notifier
{
    /**
     * Tells the people who can approve that a run is waiting.
     */
    public function approvalRequested(Agent $agent, Run $run, PendingApproval $pending): void;
}
