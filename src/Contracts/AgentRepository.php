<?php

declare(strict_types=1);

namespace WPAgents\Contracts;

use WPAgents\Domain\Agent\Agent;
use WPAgents\Domain\Agent\TriggerType;

interface AgentRepository
{
    public function find(int $id): ?Agent;

    /**
     * Active agents that have a trigger of the given type.
     *
     * @return list<Agent>
     */
    public function findActiveByTrigger(TriggerType $type): array;

    /**
     * @return list<Agent>
     */
    public function all(): array;
}
