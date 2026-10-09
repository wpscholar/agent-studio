<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Agent;

/**
 * An agent: instructions, what starts it, and what it is allowed to do.
 */
final class Agent
{
    public const MIN_STEPS = 1;
    public const MAX_STEPS = 50;
    public const MAX_RUNS_PER_HOUR_LIMIT = 1000;

    public readonly int $maxSteps;
    public readonly int $maxRunsPerHour;

    /**
     * @param list<Trigger> $triggers
     * @param list<string>  $abilities  Allowlist of ability names the agent may call.
     * @param list<string>  $skills     Allowlist of skill slugs the agent may load.
     * @param string        $provider   AI provider ID for $model. Both empty means WordPress picks.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $instructions,
        public readonly AgentStatus $status = AgentStatus::Paused,
        public readonly array $triggers = [],
        public readonly array $abilities = [],
        public readonly array $skills = [],
        public readonly ApprovalMode $approvalMode = ApprovalMode::Writes,
        public readonly int $runAsUserId = 0,
        int $maxSteps = 10,
        int $maxRunsPerHour = 30,
        public readonly string $model = '',
        public readonly string $webhookSecret = '',
        public readonly string $provider = '',
    ) {
        $this->maxSteps = max(self::MIN_STEPS, min(self::MAX_STEPS, $maxSteps));
        $this->maxRunsPerHour = max(1, min(self::MAX_RUNS_PER_HOUR_LIMIT, $maxRunsPerHour));
    }

    public function isActive(): bool
    {
        return $this->status === AgentStatus::Active;
    }

    public function allowsAbility(string $abilityName): bool
    {
        return in_array($abilityName, $this->abilities, true);
    }

    public function allowsSkill(string $slug): bool
    {
        return in_array($slug, $this->skills, true);
    }

    public function triggerOfType(TriggerType $type): ?Trigger
    {
        foreach ($this->triggers as $trigger) {
            if ($trigger->type === $type) {
                return $trigger;
            }
        }

        return null;
    }

    public function hasTrigger(TriggerType $type): bool
    {
        return $this->triggerOfType($type) !== null;
    }

    /**
     * Whether any of the agent's triggers matches the event.
     */
    public function matches(TriggerEvent $event): bool
    {
        foreach ($this->triggers as $trigger) {
            if ($trigger->matches($event)) {
                return true;
            }
        }

        return false;
    }
}
