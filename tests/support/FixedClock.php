<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use DateTimeZone;
use AgentStudio\Contracts\Clock;

final class FixedClock implements Clock
{
    public function __construct(public int $now = 1_800_000_000, public string $timezone = 'UTC')
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
