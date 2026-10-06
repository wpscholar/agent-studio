<?php

declare(strict_types=1);

namespace WPAgents\Application\Retention;

use WPAgents\Contracts\Clock;
use WPAgents\Contracts\RunRepository;
use WPAgents\Contracts\Settings;

final class PruneRunsHandler
{
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        private readonly RunRepository $runs,
        private readonly Settings $settings,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return int How many runs were deleted.
     */
    public function handle(): int
    {
        $days = $this->settings->runRetentionDays();
        if ($days <= 0) {
            return 0;
        }

        return $this->runs->pruneOlderThan($this->clock->now() - $days * self::SECONDS_PER_DAY);
    }
}
