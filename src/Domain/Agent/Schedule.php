<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Agent;

use DateTimeImmutable;

/**
 * A timetable for the schedule trigger: every hour, every day or every week,
 * at a time of day in the site's timezone.
 *
 * Trigger config keys: every (hourly|daily|weekly), at (HH:MM), day (monday…sunday).
 * For hourly schedules only the minutes of "at" count.
 */
final class Schedule
{
    public const HOURLY = 'hourly';
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';

    public const FREQUENCIES = [self::HOURLY, self::DAILY, self::WEEKLY];
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private function __construct(
        public readonly string $every,
        public readonly int $hour,
        public readonly int $minute,
        public readonly string $day,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): ?self
    {
        $every = is_string($config['every'] ?? null) ? $config['every'] : '';
        if (! in_array($every, self::FREQUENCIES, true)) {
            return null;
        }

        $at = is_string($config['at'] ?? null) ? $config['at'] : '';
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $at, $m) !== 1) {
            $m = [0, '9', '00'];
        }

        $day = $config['day'] ?? null;
        $day = is_string($day) && in_array($day, self::DAYS, true) ? $day : 'monday';

        return new self($every, (int) $m[1], (int) $m[2], $day);
    }

    /**
     * The cleaned trigger config.
     *
     * @return array{every: string, at: string, day?: string}
     */
    public function toConfig(): array
    {
        $config = ['every' => $this->every, 'at' => sprintf('%02d:%02d', $this->hour, $this->minute)];
        if ($this->every === self::WEEKLY) {
            $config['day'] = $this->day;
        }

        return $config;
    }

    /**
     * The most recent time the schedule was due, at or before $now (in $now's timezone).
     */
    public function latestSlot(DateTimeImmutable $now): DateTimeImmutable
    {
        if ($this->every === self::HOURLY) {
            $slot = $now->setTime((int) $now->format('G'), $this->minute);

            return $slot > $now ? $slot->modify('-1 hour') : $slot;
        }

        $slot = $now->setTime($this->hour, $this->minute);

        if ($this->every === self::DAILY) {
            return $slot > $now ? $slot->modify('-1 day') : $slot;
        }

        $back = ((int) $now->format('N') - (array_search($this->day, self::DAYS, true) + 1) + 7) % 7;
        $slot = $slot->modify("-{$back} days");

        return $slot > $now ? $slot->modify('-7 days') : $slot;
    }

    /**
     * The first time the schedule is due after $now.
     */
    public function nextSlot(DateTimeImmutable $now): DateTimeImmutable
    {
        $step = match ($this->every) {
            self::HOURLY => '+1 hour',
            self::DAILY => '+1 day',
            default => '+7 days',
        };

        return $this->latestSlot($now)->modify($step);
    }

    /**
     * Changes whenever the timetable changes, so a new timetable starts fresh.
     */
    public function signature(): string
    {
        return implode('|', $this->toConfig());
    }

    /**
     * Plain-English description for the model, for example "every Monday at 09:00".
     */
    public function describe(): string
    {
        return match ($this->every) {
            self::HOURLY => sprintf('every hour at %02d minutes past', $this->minute),
            self::DAILY => sprintf('every day at %02d:%02d', $this->hour, $this->minute),
            default => sprintf('every %s at %02d:%02d', ucfirst($this->day), $this->hour, $this->minute),
        };
    }
}
