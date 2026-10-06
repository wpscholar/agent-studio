<?php

declare(strict_types=1);

namespace WPAgents\Contracts;

interface Clock
{
    /**
     * Unix timestamp.
     */
    public function now(): int;
}
