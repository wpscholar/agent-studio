<?php

declare(strict_types=1);

namespace WPAgents\Tests\Support;

use WPAgents\Contracts\AgentRepository;
use WPAgents\Domain\Agent\Agent;
use WPAgents\Domain\Agent\TriggerType;

final class InMemoryAgentRepository implements AgentRepository
{
    /** @var array<int, Agent> */
    private array $agents = [];

    public function add(Agent $agent): void
    {
        $this->agents[$agent->id] = $agent;
    }

    public function find(int $id): ?Agent
    {
        return $this->agents[$id] ?? null;
    }

    public function findActiveByTrigger(TriggerType $type): array
    {
        return array_values(array_filter(
            $this->agents,
            static fn (Agent $agent): bool => $agent->isActive() && $agent->hasTrigger($type)
        ));
    }

    public function all(): array
    {
        return array_values($this->agents);
    }
}
