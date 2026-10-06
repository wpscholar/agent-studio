<?php

declare(strict_types=1);

namespace WPAgents\Application\Execute;

use WPAgents\Contracts\AbilityGateway;
use WPAgents\Contracts\SkillRepository;
use WPAgents\Domain\Agent\Agent;

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
