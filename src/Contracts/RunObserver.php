<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Domain\Run\Run;

/**
 * Told when a run stops: finished, failed, or waiting for approval.
 */
interface RunObserver
{
    public function runStopped(Run $run): void;
}
