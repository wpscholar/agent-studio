<?php

declare(strict_types=1);

namespace AgentStudio\Application\Execute;

use AgentStudio\Contracts\Model\ToolSpec;
use AgentStudio\Domain\Ability\AbilityInfo;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Skill\Skill;

/**
 * The tools one agent can use: its allowlisted abilities that exist right now,
 * plus the built-in skill loader when the agent has skills.
 */
final class ToolRegistry
{
    public const LOAD_SKILL = 'load_skill';

    /**
     * @param array<string, AbilityInfo> $abilities Keyed by tool name.
     * @param array<string, Skill>       $skills    Keyed by slug.
     * @param list<string>               $missing   Allowlisted abilities that are not registered.
     */
    private function __construct(
        private readonly array $abilities,
        private readonly array $skills,
        private readonly array $missing,
    ) {
    }

    /**
     * @param list<AbilityInfo> $available
     * @param list<Skill>       $skills
     */
    public static function build(Agent $agent, array $available, array $skills): self
    {
        $byName = [];
        foreach ($available as $info) {
            $byName[$info->name] = $info;
        }

        $abilities = [];
        $missing = [];
        foreach ($agent->abilities as $name) {
            if (isset($byName[$name])) {
                $abilities[$byName[$name]->toolName()] = $byName[$name];
            } else {
                $missing[] = $name;
            }
        }

        $allowedSkills = [];
        foreach ($skills as $skill) {
            if ($agent->allowsSkill($skill->slug)) {
                $allowedSkills[$skill->slug] = $skill;
            }
        }

        return new self($abilities, $allowedSkills, $missing);
    }

    /**
     * @return list<ToolSpec>
     */
    public function specs(): array
    {
        $specs = [];
        foreach ($this->abilities as $toolName => $info) {
            $schema = $info->inputSchema !== [] ? $info->inputSchema : ['type' => 'object', 'properties' => []];
            $specs[] = new ToolSpec($toolName, $info->label . ': ' . $info->description, $schema);
        }

        if ($this->skills !== []) {
            $specs[] = new ToolSpec(
                self::LOAD_SKILL,
                'Load the full instructions of one of your skills.',
                [
                    'type' => 'object',
                    'properties' => [
                        'slug' => [
                            'type' => 'string',
                            'enum' => array_keys($this->skills),
                            'description' => 'The skill to load.',
                        ],
                    ],
                    'required' => ['slug'],
                ]
            );
        }

        return $specs;
    }

    public function ability(string $toolName): ?AbilityInfo
    {
        return $this->abilities[$toolName] ?? null;
    }

    public function isLoadSkillTool(string $toolName): bool
    {
        return $toolName === self::LOAD_SKILL && $this->skills !== [];
    }

    public function skill(string $slug): ?Skill
    {
        return $this->skills[$slug] ?? null;
    }

    /**
     * @return list<Skill>
     */
    public function skills(): array
    {
        return array_values($this->skills);
    }

    /**
     * @return list<string>
     */
    public function missingAbilities(): array
    {
        return $this->missing;
    }
}
