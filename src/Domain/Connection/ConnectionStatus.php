<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Connection;

enum ConnectionStatus: string
{
    /** Saved but never checked. */
    case New = 'new';

    /** Checked, and its tools are listed. */
    case Connected = 'connected';

    /** The service wants someone to sign in (again). */
    case NeedsSignIn = 'needs_sign_in';

    /** The last check failed for another reason; see the message. */
    case Problem = 'problem';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::New;
    }
}
