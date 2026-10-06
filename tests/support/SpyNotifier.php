<?php

declare(strict_types=1);

namespace WPAgents\Tests\Support;

use WPAgents\Contracts\Notifier;
use WPAgents\Domain\Agent\Agent;
use WPAgents\Domain\Run\PendingApproval;
use WPAgents\Domain\Run\Run;

final class SpyNotifier implements Notifier
{
    /** @var list<array{agent: int, run: int|null, ability: string}> */
    public array $notifications = [];

    public function approvalRequested(Agent $agent, Run $run, PendingApproval $pending): void
    {
        $this->notifications[] = ['agent' => $agent->id, 'run' => $run->id(), 'ability' => $pending->abilityName];
    }
}
