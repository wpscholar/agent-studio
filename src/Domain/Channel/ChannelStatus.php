<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Channel;

enum ChannelStatus: string
{
    /** Saved, not switched on yet. */
    case New = 'new';

    /** Switched on and working. */
    case Active = 'active';

    /** Switched off by a person. Nothing is sent or received. */
    case Off = 'off';

    /** Switching on, or the last message, failed; see the message. */
    case Problem = 'problem';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::New;
    }
}
