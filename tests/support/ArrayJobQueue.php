<?php

declare(strict_types=1);

namespace WPAgents\Tests\Support;

use WPAgents\Contracts\JobQueue;

final class ArrayJobQueue implements JobQueue
{
    /** @var list<int> */
    public array $jobs = [];

    public function enqueueRun(int $runId): void
    {
        $this->jobs[] = $runId;
    }
}
