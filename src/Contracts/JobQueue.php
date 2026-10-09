<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

interface JobQueue
{
    /**
     * Asks the background worker to execute the run soon.
     */
    public function enqueueRun(int $runId): void;
}
