<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\JobQueue;

final class ArrayJobQueue implements JobQueue
{
    /** @var list<int> */
    public array $jobs = [];

    public function enqueueRun(int $runId): void
    {
        $this->jobs[] = $runId;
    }
}
