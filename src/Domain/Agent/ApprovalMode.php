<?php

declare(strict_types=1);

namespace WPAgents\Domain\Agent;

use WPAgents\Domain\Ability\Risk;

/**
 * Decides which ability calls need a human to approve them first.
 *
 * Reading never needs approval.
 */
enum ApprovalMode: string
{
    /** Approve everything that changes something (default, safest). */
    case Writes = 'writes';

    /** Approve only destructive and external actions. */
    case Destructive = 'destructive';

    /** Never ask. Only for fully trusted, narrowly scoped agents. */
    case Never = 'never';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Writes;
    }

    public function requiresApproval(Risk $risk): bool
    {
        return match ($this) {
            self::Writes => $risk !== Risk::Read,
            self::Destructive => $risk === Risk::Destructive || $risk === Risk::External,
            self::Never => false,
        };
    }
}
