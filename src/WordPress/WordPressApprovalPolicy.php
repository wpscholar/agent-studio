<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use AgentStudio\Contracts\ApprovalPolicy;
use AgentStudio\Domain\Agent\Agent;

final class WordPressApprovalPolicy implements ApprovalPolicy
{
    public function canApprove(Agent $agent, int $userId): bool
    {
        return Capabilities::canApprove($agent, $userId);
    }

    public function canChat(int $userId): bool
    {
        return Capabilities::canChat($userId);
    }
}
