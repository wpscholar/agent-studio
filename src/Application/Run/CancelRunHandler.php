<?php

declare(strict_types=1);

namespace AgentStudio\Application\Run;

use AgentStudio\Contracts\Clock;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Domain\Run\RunStatus;

final class CancelRunHandler
{
    public function __construct(
        private readonly RunRepository $runs,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return bool False when the run does not exist or can no longer be cancelled.
     */
    public function handle(int $runId): bool
    {
        $run = $this->runs->find($runId);
        if ($run === null) {
            return false;
        }

        if ($run->status() !== RunStatus::Queued && $run->status() !== RunStatus::AwaitingApproval) {
            return false;
        }

        $run->cancel($this->clock->now());
        $this->runs->save($run);

        return true;
    }
}
