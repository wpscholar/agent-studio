<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Connection;

/**
 * How Agent Studio proves who it is to the other service.
 */
enum AuthMode: string
{
    /** The service is open; nothing to send. */
    case None = 'none';

    /** An API key or token pasted by the site owner, sent in a header. */
    case Key = 'key';

    /** The site owner signs in to the service, which issues tokens (OAuth). */
    case OAuth = 'oauth';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::None;
    }
}
