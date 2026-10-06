<?php

declare(strict_types=1);

namespace WPAgents\Tests\Support;

use WPAgents\Contracts\Clock;

final class FixedClock implements Clock
{
    public function __construct(public int $now = 1_800_000_000)
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
