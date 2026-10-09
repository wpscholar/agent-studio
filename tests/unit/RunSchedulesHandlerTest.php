<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use AgentStudio\Application\Schedule\RunSchedulesHandler;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\AgentStatus;
use AgentStudio\Domain\Agent\Trigger;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Tests\Support\InMemoryScheduleState;
use AgentStudio\Tests\Support\TestWorld;

final class RunSchedulesHandlerTest extends TestCase
{
    private TestWorld $world;
    private InMemoryScheduleState $state;
    private RunSchedulesHandler $handler;

    protected function setUp(): void
    {
        $this->world = new TestWorld();
        $this->state = new InMemoryScheduleState();
        $this->handler = new RunSchedulesHandler(
            $this->world->agents,
            $this->world->dispatch,
            $this->state,
            $this->world->clock
        );
        $this->setTime('2027-01-15 08:00');
    }

    private function setTime(string $time): void
    {
        $zone = new DateTimeZone($this->world->clock->timezone);
        $this->world->clock->now = (new DateTimeImmutable($time, $zone))->getTimestamp();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function scheduled(
        int $id,
        array $config = ['every' => 'daily', 'at' => '09:00'],
        AgentStatus $status = AgentStatus::Active
    ): Agent {
        $agent = new Agent(
            id: $id,
            name: 'Daily',
            instructions: 'Report.',
            status: $status,
            triggers: [new Trigger(TriggerType::Schedule, $config)],
            runAsUserId: TestWorld::RUN_AS_USER
        );
        $this->world->agents->add($agent);

        return $agent;
    }

    public function testTheFirstCheckOnlyArmsTheSchedule(): void
    {
        $this->scheduled(1);

        $this->assertSame([], $this->handler->handle());
        $this->assertCount(0, $this->world->runs->recent(10));
    }

    public function testEachSlotRunsOnce(): void
    {
        $this->scheduled(1);
        $this->handler->handle();

        $this->setTime('2027-01-15 09:02');
        $this->assertSame([1], $this->handler->handle());

        $this->setTime('2027-01-15 09:07');
        $this->assertSame([], $this->handler->handle());

        $runs = $this->world->runs->recent(10);
        $this->assertCount(1, $runs);
        $this->assertSame(TriggerType::Schedule, $runs[0]->triggerType());
        $this->assertSame('every day at 09:00', $runs[0]->input()['schedule']);
        $this->assertSame('2027-01-15T09:00:00+00:00', $runs[0]->input()['scheduled_for']);
    }

    public function testMissedSlotsRunOnceNotOncePerSlot(): void
    {
        $this->scheduled(1);
        $this->handler->handle();

        $this->setTime('2027-01-18 12:00');
        $this->assertSame([1], $this->handler->handle());
        $this->assertCount(1, $this->world->runs->recent(10));
        $this->assertSame('2027-01-18T09:00:00+00:00', $this->world->runs->recent(10)[0]->input()['scheduled_for']);
    }

    public function testChangingTheTimetableStartsFresh(): void
    {
        $this->scheduled(1);
        $this->handler->handle();

        $this->scheduled(1, ['every' => 'hourly', 'at' => '00:30']);
        $this->setTime('2027-01-15 09:45');

        $this->assertSame([], $this->handler->handle());
    }

    public function testPausedAgentsAreForgottenAndRearmedWhenActiveAgain(): void
    {
        $this->scheduled(1);
        $this->handler->handle();

        $this->scheduled(1, status: AgentStatus::Paused);
        $this->setTime('2027-01-15 09:30');
        $this->handler->handle();
        $this->assertSame([], $this->state->state);

        // Switched back on after its slot passed: no catch-up run.
        $this->scheduled(1);
        $this->assertSame([], $this->handler->handle());
        $this->assertCount(0, $this->world->runs->recent(10));
    }

    public function testSchedulesUseTheSiteTimezone(): void
    {
        $this->world->clock->timezone = 'America/New_York';
        $this->setTime('2027-01-15 08:00');
        $this->scheduled(1);
        $this->handler->handle();

        $this->setTime('2027-01-15 09:00');
        $this->assertSame([1], $this->handler->handle());
        $this->assertSame('2027-01-15T09:00:00-05:00', $this->world->runs->recent(10)[0]->input()['scheduled_for']);
    }
}
