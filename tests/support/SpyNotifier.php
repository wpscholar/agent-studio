<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\Notifier;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Run\PendingApproval;
use AgentStudio\Domain\Run\Run;

final class SpyNotifier implements Notifier
{
    /** @var list<array{agent: int, run: int|null, ability: string}> */
    public array $notifications = [];

    public function approvalRequested(Agent $agent, Run $run, PendingApproval $pending): void
    {
        $this->notifications[] = ['agent' => $agent->id, 'run' => $run->id(), 'ability' => $pending->abilityName];
    }
}
