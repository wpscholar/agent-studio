<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Domain\Agent\AgentStatus;

/**
 * Converts between an agent's status and the WordPress post status that stores it.
 */
final class AgentPostStatus
{
    public static function toPost(AgentStatus $status): string
    {
        return match ($status) {
            AgentStatus::Active => 'publish',
            AgentStatus::Paused => PostTypes::STATUS_PAUSED,
            AgentStatus::Draft => 'draft',
        };
    }

    /**
     * Any status other than published or paused (draft, pending, scheduled, private) counts as a draft.
     */
    public static function fromPost(string $postStatus): AgentStatus
    {
        return match ($postStatus) {
            'publish' => AgentStatus::Active,
            PostTypes::STATUS_PAUSED => AgentStatus::Paused,
            default => AgentStatus::Draft,
        };
    }

    /**
     * Post statuses agents can have, for queries.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return ['publish', PostTypes::STATUS_PAUSED, 'draft', 'pending', 'private', 'future'];
    }
}
