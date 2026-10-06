<?php

declare(strict_types=1);

namespace WPAgents\Application\Dispatch;

use WPAgents\Contracts\AgentRepository;
use WPAgents\Contracts\Clock;
use WPAgents\Contracts\JobQueue;
use WPAgents\Contracts\RecursionGuard;
use WPAgents\Contracts\RunRepository;
use WPAgents\Domain\Agent\Agent;
use WPAgents\Domain\Agent\TriggerEvent;
use WPAgents\Domain\Agent\TriggerType;
use WPAgents\Domain\Run\Run;

/**
 * Turns an event into queued runs for every agent that should react to it.
 */
final class DispatchTriggerHandler
{
    private const HOUR = 3600;

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly JobQueue $queue,
        private readonly RecursionGuard $guard,
        private readonly Clock $clock,
    ) {
    }

    public function handle(TriggerEvent $event): DispatchResult
    {
        // Events caused by an agent's own actions never start agents. This is what
        // stops an agent that publishes a post from triggering itself forever.
        if ($this->guard->active()) {
            return DispatchResult::suppressed();
        }

        $dispatches = [];
        foreach ($this->candidates($event) as $agent) {
            $dispatches[] = $this->dispatchTo($agent, $event);
        }

        return new DispatchResult($dispatches);
    }

    /**
     * @return list<Agent>
     */
    private function candidates(TriggerEvent $event): array
    {
        if ($event->agentId !== null) {
            $agent = $this->agents->find($event->agentId);

            return $agent === null ? [] : [$agent];
        }

        return $this->agents->findActiveByTrigger($event->type);
    }

    private function dispatchTo(Agent $agent, TriggerEvent $event): AgentDispatch
    {
        $manual = $event->type === TriggerType::Manual;

        // Manual runs are explicit requests, so they ignore status and filters.
        if (! $manual && (! $agent->isActive() || ! $agent->matches($event))) {
            return new AgentDispatch($agent->id, DispatchOutcome::Skipped);
        }

        $now = $this->clock->now();

        if ($event->dedupeKey !== '') {
            $existing = $this->runs->findByDedupeKey($agent->id, $event->dedupeKey, $now - $event->dedupeWindow);
            if ($existing !== null) {
                return new AgentDispatch($agent->id, DispatchOutcome::Duplicate, $existing->id());
            }
        }

        $run = new Run(null, $agent->id, $event->type, $event->payload, $now, $event->dedupeKey);

        if (! $manual && $this->runs->countSince($agent->id, $now - self::HOUR) >= $agent->maxRunsPerHour) {
            $run->skip(
                sprintf('Rate limit reached: this agent runs at most %d times per hour.', $agent->maxRunsPerHour),
                $now
            );
            $this->runs->add($run);

            return new AgentDispatch($agent->id, DispatchOutcome::RateLimited, $run->id());
        }

        $this->runs->add($run);
        $this->queue->enqueueRun((int) $run->id());

        return new AgentDispatch($agent->id, DispatchOutcome::Queued, $run->id());
    }
}
