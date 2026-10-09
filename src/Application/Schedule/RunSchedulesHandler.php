<?php

declare(strict_types=1);

namespace AgentStudio\Application\Schedule;

use DateTimeImmutable;
use Throwable;
use AgentStudio\Application\Dispatch\DispatchTriggerHandler;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\Clock;
use AgentStudio\Contracts\ScheduleState;
use AgentStudio\Domain\Agent\Schedule;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;

/**
 * Called every few minutes. Starts each active scheduled agent once per due slot.
 *
 * A timetable is armed the first time it is seen, so saving or switching on an
 * agent never fires a slot that was already in the past. If the site was quiet
 * and several slots passed, only the latest one runs.
 */
final class RunSchedulesHandler
{
    // Longer than a week, so a weekly slot can never be dispatched twice.
    private const DEDUPE_WINDOW = 8 * 86400;

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly DispatchTriggerHandler $dispatch,
        private readonly ScheduleState $state,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<int> IDs of the agents handed to the dispatcher.
     */
    public function handle(): array
    {
        $now = (new DateTimeImmutable('@' . $this->clock->now()))->setTimezone($this->clock->timezone());
        $seen = [];
        $started = [];

        foreach ($this->agents->findActiveByTrigger(TriggerType::Schedule) as $agent) {
            $trigger = $agent->triggerOfType(TriggerType::Schedule);
            $schedule = $trigger === null ? null : Schedule::fromConfig($trigger->config);
            if ($schedule === null) {
                continue;
            }

            $seen[] = $agent->id;
            $slot = $schedule->latestSlot($now);
            $last = $this->state->lastSlot($agent->id, $schedule->signature());
            $this->state->remember($agent->id, $schedule->signature(), $slot->getTimestamp());

            if ($last === null || $slot->getTimestamp() <= $last) {
                continue;
            }

            try {
                $this->dispatch->handle(
                    TriggerEvent::targeted(
                        TriggerType::Schedule,
                        $agent->id,
                        ['scheduled_for' => $slot->format(DATE_ATOM), 'schedule' => $schedule->describe()],
                        'schedule:' . $slot->getTimestamp(),
                        self::DEDUPE_WINDOW
                    )
                );
                $started[] = $agent->id;
            } catch (Throwable) {
                // One broken agent must not stop the others. The slot is not retried.
            }
        }

        $this->state->keepOnly($seen);

        return $started;
    }
}
