<?php

declare(strict_types=1);

namespace WPAgents\Contracts;

use WPAgents\Contracts\Ability\AbilityResult;
use WPAgents\Domain\Ability\AbilityInfo;

/**
 * The abilities registered on the site, and a way to call them.
 */
interface AbilityGateway
{
    /**
     * Abilities an agent could be allowed to use.
     *
     * @return list<AbilityInfo>
     */
    public function available(): array;

    /**
     * Calls an ability as the current user. Permission checks belong to the ability.
     *
     * @param array<string, mixed> $input
     */
    public function execute(string $abilityName, array $input): AbilityResult;
}
