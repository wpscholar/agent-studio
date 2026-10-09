<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Run;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** The run was never started, for example because a rate limit was hit. */
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled, self::Skipped => true,
            default => false,
        };
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Failed;
    }
}
