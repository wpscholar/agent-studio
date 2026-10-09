<?php

declare(strict_types=1);

namespace AgentStudio\Application\Dispatch;

enum DispatchOutcome: string
{
    /** A run was created and queued. */
    case Queued = 'queued';

    /** The same event was already handled recently. */
    case Duplicate = 'duplicate';

    /** The agent hit its hourly run limit. */
    case RateLimited = 'rate_limited';

    /** The agent was paused or the event did not match its filters. */
    case Skipped = 'skipped';
}
