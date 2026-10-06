<?php

declare(strict_types=1);

namespace WPAgents\Infrastructure;

use WPAgents\Contracts\JobQueue;

/**
 * Runs agents in the background, so the request that triggered an agent
 * (a post save, a signup, a webhook) returns immediately.
 *
 * Uses Action Scheduler when it is installed (WooCommerce ships it), and
 * WP-Cron otherwise.
 */
final class WordPressJobQueue implements JobQueue
{
    public const HOOK = 'wpa_execute_run';
    private const GROUP = 'wp-agents';

    public function enqueueRun(int $runId): void
    {
        // A unique token keeps WP-Cron from dropping a re-queued run as a duplicate event.
        $args = [$runId, wp_generate_uuid4()];

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::HOOK, $args, self::GROUP);

            return;
        }

        wp_schedule_single_event(time(), self::HOOK, $args);

        if (function_exists('spawn_cron')) {
            spawn_cron();
        }
    }
}
