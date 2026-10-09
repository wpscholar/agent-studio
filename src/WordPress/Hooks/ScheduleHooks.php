<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Hooks;

use AgentStudio\Application\Schedule\RunSchedulesHandler;

/**
 * Checks scheduled agents every five minutes through WP-Cron. The runs they
 * start go through the normal job queue.
 */
final class ScheduleHooks
{
    public const HOOK = 'agent_studio_check_schedules';
    public const INTERVAL = 'agent_studio_five_minutes';

    public function __construct(private readonly RunSchedulesHandler $schedules)
    {
    }

    public function register(): void
    {
        add_filter('cron_schedules', [self::class, 'addInterval']);
        add_action(self::HOOK, [$this, 'check']);
        add_action('init', [self::class, 'scheduleChecks']);
    }

    /**
     * @param array<string, array{interval: int, display: string}> $schedules
     *
     * @return array<string, array{interval: int, display: string}>
     */
    public static function addInterval(array $schedules): array
    {
        $schedules[self::INTERVAL] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every five minutes (Agent Studio)', 'agent-studio'),
        ];

        return $schedules;
    }

    public static function scheduleChecks(): void
    {
        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), self::INTERVAL, self::HOOK);
        }
    }

    public function check(): void
    {
        $this->schedules->handle();
    }
}
