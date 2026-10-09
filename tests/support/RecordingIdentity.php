<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\Identity;

final class RecordingIdentity implements Identity
{
    public int $current = 0;

    /** @var list<int> */
    public array $switchedTo = [];

    public function runAs(int $userId, callable $callback): mixed
    {
        $previous = $this->current;
        $this->current = $userId;
        $this->switchedTo[] = $userId;

        try {
            return $callback();
        } finally {
            $this->current = $previous;
        }
    }
}
