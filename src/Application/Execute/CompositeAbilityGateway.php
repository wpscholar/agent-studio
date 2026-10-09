<?php

declare(strict_types=1);

namespace AgentStudio\Application\Execute;

use AgentStudio\Application\Connections\ConnectionAbilityGateway;
use AgentStudio\Contracts\Ability\AbilityResult;
use AgentStudio\Contracts\AbilityGateway;

/**
 * The site's own abilities plus the tools of connected services, as one list.
 */
final class CompositeAbilityGateway implements AbilityGateway
{
    public function __construct(
        private readonly AbilityGateway $site,
        private readonly ConnectionAbilityGateway $connections,
    ) {
    }

    public function available(): array
    {
        return array_merge($this->site->available(), $this->connections->available());
    }

    public function execute(string $abilityName, array $input): AbilityResult
    {
        return $this->connections->handles($abilityName)
            ? $this->connections->execute($abilityName, $input)
            : $this->site->execute($abilityName, $input);
    }
}
