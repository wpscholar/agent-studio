<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Hooks;

use WPAgents\Application\Execute\ExecuteRunHandler;
use WPAgents\Application\Retention\PruneRunsHandler;
use WPAgents\Infrastructure\WordPressJobQueue;

/**
 * The background side: executing queued runs and pruning old ones.
 */
final class WorkerHooks
{
    public const PRUNE_HOOK = 'wpa_prune_runs';

    public function __construct(
        private readonly ExecuteRunHandler $execute,
        private readonly PruneRunsHandler $prune,
    ) {
    }

    public function register(): void
    {
        add_action(WordPressJobQueue::HOOK, [$this, 'executeRun'], 10, 1);
        add_action(self::PRUNE_HOOK, [$this, 'pruneRuns']);
        add_action('init', [self::class, 'schedulePruning']);
    }

    /**
     * The queue also passes a unique token that only keeps duplicate events apart.
     */
    public function executeRun(int $runId): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $this->execute->handle($runId);
    }

    public function pruneRuns(): void
    {
        $this->prune->handle();
    }

    public static function schedulePruning(): void
    {
        if (! wp_next_scheduled(self::PRUNE_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK);
        }
    }
}
