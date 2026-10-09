<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Agent;

enum AgentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Paused;
    }
}
