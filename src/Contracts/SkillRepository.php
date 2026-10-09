<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Domain\Skill\Skill;

interface SkillRepository
{
    /**
     * @return list<Skill>
     */
    public function all(): array;

    public function findBySlug(string $slug): ?Skill;

    /**
     * Creates the skill or replaces the one with the same slug.
     */
    public function save(Skill $skill): void;
}
