<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Domain\Agent\Agent;

/**
 * Who may approve an agent's actions, and who may talk to agents.
 */
interface ApprovalPolicy
{
    public function canApprove(Agent $agent, int $userId): bool;

    public function canChat(int $userId): bool;
}
