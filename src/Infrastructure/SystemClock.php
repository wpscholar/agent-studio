<?php

declare(strict_types=1);

namespace WPAgents\Infrastructure;

use WPAgents\Contracts\Clock;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
