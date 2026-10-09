<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

interface Settings
{
    /**
     * How long finished runs are kept. Zero keeps them forever.
     */
    public function runRetentionDays(): int;
}
