<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use AgentStudio\Domain\Agent\Schedule;

final class ScheduleTest extends TestCase
{
    private function time(string $time, string $zone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone($zone));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function schedule(array $config): Schedule
    {
        $schedule = Schedule::fromConfig($config);
        $this->assertNotNull($schedule);

        return $schedule;
    }

    public function testUnknownFrequencyIsNotASchedule(): void
    {
        $this->assertNull(Schedule::fromConfig(['every' => 'fortnightly']));
        $this->assertNull(Schedule::fromConfig([]));
    }

    public function testBadTimesAndDaysFallBackToDefaults(): void
    {
        $schedule = $this->schedule(['every' => 'weekly', 'at' => '25:99', 'day' => 'someday']);

        $this->assertSame(['every' => 'weekly', 'at' => '09:00', 'day' => 'monday'], $schedule->toConfig());
    }

    public function testOnlyWeeklySchedulesKeepADay(): void
    {
        $schedule = $this->schedule(['every' => 'daily', 'at' => '7:30', 'day' => 'friday']);

        $this->assertSame(['every' => 'daily', 'at' => '07:30'], $schedule->toConfig());
    }

    public function testHourlyUsesTheMinutes(): void
    {
        $schedule = $this->schedule(['every' => 'hourly', 'at' => '09:15']);

        $this->assertEquals($this->time('2027-01-15 10:15'), $schedule->latestSlot($this->time('2027-01-15 10:20')));
        $this->assertEquals($this->time('2027-01-15 09:15'), $schedule->latestSlot($this->time('2027-01-15 10:14')));
        $this->assertEquals($this->time('2027-01-15 10:15'), $schedule->latestSlot($this->time('2027-01-15 10:15')));
    }

    public function testDailyIsTodayOnceTheTimeHasPassed(): void
    {
        $schedule = $this->schedule(['every' => 'daily', 'at' => '09:00']);

        $this->assertEquals($this->time('2027-01-14 09:00'), $schedule->latestSlot($this->time('2027-01-15 08:59')));
        $this->assertEquals($this->time('2027-01-15 09:00'), $schedule->latestSlot($this->time('2027-01-15 09:01')));
    }

    public function testWeeklyFindsTheLastMatchingDay(): void
    {
        $schedule = $this->schedule(['every' => 'weekly', 'at' => '09:00', 'day' => 'monday']);

        // 2027-01-15 is a Friday; 2027-01-11 the Monday before.
        $this->assertEquals($this->time('2027-01-11 09:00'), $schedule->latestSlot($this->time('2027-01-15 12:00')));
        $this->assertEquals($this->time('2027-01-04 09:00'), $schedule->latestSlot($this->time('2027-01-11 08:00')));
        $this->assertEquals($this->time('2027-01-11 09:00'), $schedule->latestSlot($this->time('2027-01-11 09:00')));
    }

    public function testNextSlotIsTheFirstOneAfterNow(): void
    {
        $daily = $this->schedule(['every' => 'daily', 'at' => '09:00']);
        $weekly = $this->schedule(['every' => 'weekly', 'at' => '09:00', 'day' => 'monday']);

        $this->assertEquals($this->time('2027-01-15 09:00'), $daily->nextSlot($this->time('2027-01-15 08:00')));
        $this->assertEquals($this->time('2027-01-16 09:00'), $daily->nextSlot($this->time('2027-01-15 09:00')));
        $this->assertEquals($this->time('2027-01-18 09:00'), $weekly->nextSlot($this->time('2027-01-15 12:00')));
    }

    public function testTimesAreInTheGivenTimezone(): void
    {
        $schedule = $this->schedule(['every' => 'daily', 'at' => '09:00']);
        $now = $this->time('2027-01-15 08:30', 'UTC')->setTimezone(new DateTimeZone('Europe/Berlin'));

        $this->assertSame('2027-01-15T09:00:00+01:00', $schedule->latestSlot($now)->format(DATE_ATOM));
    }

    public function testDescriptionAndSignature(): void
    {
        $schedule = $this->schedule(['every' => 'weekly', 'at' => '18:05', 'day' => 'friday']);

        $this->assertSame('every Friday at 18:05', $schedule->describe());
        $monday = $this->schedule(['every' => 'weekly', 'at' => '18:05', 'day' => 'monday']);
        $this->assertNotSame($schedule->signature(), $monday->signature());
    }
}
