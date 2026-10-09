<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Contracts\RecursionGuard;

/**
 * Tracks, for the current PHP process, whether an agent is acting.
 */
final class ProcessRecursionGuard implements RecursionGuard
{
    private int $depth = 0;

    public function active(): bool
    {
        return $this->depth > 0;
    }

    public function run(callable $callback): mixed
    {
        $this->depth++;

        try {
            return $callback();
        } finally {
            $this->depth--;
        }
    }
}
