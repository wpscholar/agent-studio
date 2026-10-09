<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Channel;

enum ContactStatus: string
{
    /** Wrote to the channel; waiting for someone to let them in. */
    case Pending = 'pending';

    /** May talk to the agent. */
    case Allowed = 'allowed';

    /** Ignored. */
    case Blocked = 'blocked';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Pending;
    }
}
