<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Agent;

/**
 * Whether an agent responds to events.
 *
 * Only active agents start on their own. Drafts and paused agents can still be run by hand,
 * so people can try an agent before switching it on.
 */
enum AgentStatus: string
{
    /** Switched on: its triggers start it. */
    case Active = 'active';

    /** Was switched on, and is switched off for now. */
    case Paused = 'paused';

    /** Still being set up. It has never been switched on. */
    case Draft = 'draft';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Paused;
    }
}
