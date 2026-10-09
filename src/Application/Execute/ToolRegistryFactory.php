<?php

declare(strict_types=1);

namespace AgentStudio\Application\Execute;

use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Contracts\SkillRepository;
use AgentStudio\Domain\Agent\Agent;

final class ToolRegistryFactory
{
    public function __construct(
        private readonly AbilityGateway $abilities,
        private readonly SkillRepository $skills,
    ) {
    }

    public function forAgent(Agent $agent): ToolRegistry
    {
        return ToolRegistry::build(
            $agent,
            $agent->abilities === [] ? [] : $this->abilities->available(),
            $agent->skills === [] ? [] : $this->skills->all()
        );
    }
}
