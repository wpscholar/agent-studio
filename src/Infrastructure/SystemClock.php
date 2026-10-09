<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use DateTimeZone;
use AgentStudio\Contracts\Clock;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }

    public function timezone(): DateTimeZone
    {
        return wp_timezone();
    }
}
