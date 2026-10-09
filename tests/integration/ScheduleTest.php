<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use AgentStudio\Application\Schedule\RunSchedulesHandler;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Infrastructure\OptionScheduleState;
use AgentStudio\WordPress\Hooks\ScheduleHooks;
use AgentStudio\WordPress\Plugin;

final class ScheduleTest extends IntegrationTestCase
{
    public function test_the_check_is_scheduled_every_five_minutes(): void
    {
        wp_clear_scheduled_hook(ScheduleHooks::HOOK);
        ScheduleHooks::scheduleChecks();

        $this->assertNotFalse(wp_next_scheduled(ScheduleHooks::HOOK));
        $this->assertSame(ScheduleHooks::INTERVAL, wp_get_schedule(ScheduleHooks::HOOK));
        $this->assertSame(300, wp_get_schedules()[ScheduleHooks::INTERVAL]['interval']);
    }

    public function test_state_is_kept_per_agent_and_timetable(): void
    {
        $state = new OptionScheduleState();
        $state->remember(5, 'daily|09:00', 100);
        $state->remember(6, 'hourly|00:15', 200);

        $this->assertSame(100, $state->lastSlot(5, 'daily|09:00'));
        $this->assertNull($state->lastSlot(5, 'daily|10:00'));

        $state->keepOnly([6]);
        $this->assertNull($state->lastSlot(5, 'daily|09:00'));
        $this->assertSame(200, $state->lastSlot(6, 'hourly|00:15'));
    }

    public function test_a_scheduled_agent_is_armed_then_queued(): void
    {
        $id = $this->createAgent(
            ['triggers' => [['type' => 'schedule', 'config' => ['every' => 'hourly', 'at' => '00:00']]]]
        );
        $handler = Plugin::container()->get(RunSchedulesHandler::class);

        $this->assertSame([], $handler->handle());

        // Pretend the last slot handled was an hour earlier.
        $state = new OptionScheduleState();
        $stored = get_option(OptionScheduleState::OPTION);
        $this->assertIsArray($stored);
        $state->remember($id, $stored[$id]['signature'], $stored[$id]['slot'] - HOUR_IN_SECONDS);

        $this->assertSame([$id], $handler->handle());
        $runs = Plugin::container()->get(RunRepository::class)->recent(5, 0, $id);
        $this->assertCount(1, $runs);
        $this->assertSame('schedule', $runs[0]->triggerType()->value);
    }
}
