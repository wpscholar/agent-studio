<?php

declare(strict_types=1);

namespace WPAgents\Domain\Skill;

use InvalidArgumentException;

/**
 * A reusable set of instructions an agent can load on demand.
 *
 * Same idea as an Agent Skill (SKILL.md): a short description that is always
 * visible to the agent, and a body that is loaded only when needed.
 */
final class Skill
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $body,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $slug) !== 1) {
            throw new InvalidArgumentException(
                'A skill slug must use lowercase letters, numbers and hyphens, up to 64 characters.'
            );
        }
    }
}
