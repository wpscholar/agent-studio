<?php

declare(strict_types=1);

namespace WPAgents\Domain\Ability;

/**
 * How much damage an ability can do. Drives the approval policy.
 */
enum Risk: string
{
    /** Reads data and changes nothing. */
    case Read = 'read';

    /** Creates or modifies site data in a recoverable way. */
    case Write = 'write';

    /** Deletes data or changes it in a way that is hard to undo. */
    case Destructive = 'destructive';

    /** Has an effect outside the site: email, HTTP calls, payments. */
    case External = 'external';

    public static function fromString(string $value, self $default = self::Write): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? $default;
    }
}
