<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use DateTimeZone;

interface Clock
{
    /**
     * Unix timestamp.
     */
    public function now(): int;

    /**
     * The site's timezone, which schedules use.
     */
    public function timezone(): DateTimeZone;
}
