<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Agent;

enum TriggerType: string
{
    /** A post changed status, for example draft to publish. */
    case PostStatus = 'post_status';

    /** A user account was created. */
    case UserRegistered = 'user_registered';

    /** A comment was submitted. */
    case CommentPosted = 'comment_posted';

    /** The agent's timetable came round (every hour, day or week). */
    case Schedule = 'schedule';

    /** An external system called the agent's signed webhook. */
    case Webhook = 'webhook';

    /** A person or script started the agent directly. */
    case Manual = 'manual';
}
