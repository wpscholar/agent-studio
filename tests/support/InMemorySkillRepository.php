<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\SkillRepository;
use AgentStudio\Domain\Skill\Skill;

final class InMemorySkillRepository implements SkillRepository
{
    /** @var array<string, Skill> */
    private array $skills = [];

    public function all(): array
    {
        return array_values($this->skills);
    }

    public function findBySlug(string $slug): ?Skill
    {
        return $this->skills[$slug] ?? null;
    }

    public function save(Skill $skill): void
    {
        $this->skills[$skill->slug] = $skill;
    }
}
