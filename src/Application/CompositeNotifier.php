<?php

declare(strict_types=1);

namespace AgentStudio\Application;

use Throwable;
use AgentStudio\Contracts\Notifier;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Run\PendingApproval;
use AgentStudio\Domain\Run\Run;

/**
 * Tells everyone: email, and every channel set up for notifications. One
 * failing notifier never stops the others.
 */
final class CompositeNotifier implements Notifier
{
    /**
     * @param list<Notifier> $notifiers
     */
    public function __construct(private readonly array $notifiers)
    {
    }

    public function approvalRequested(Agent $agent, Run $run, PendingApproval $pending): void
    {
        foreach ($this->notifiers as $notifier) {
            try {
                $notifier->approvalRequested($agent, $run, $pending);
            } catch (Throwable) {
                // Keep going: an unreachable chat service must not hide the email.
            }
        }
    }
}
