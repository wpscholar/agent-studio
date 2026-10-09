<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Contracts\Settings;

final class WordPressSettings implements Settings
{
    public const OPTION = 'agent_studio_settings';
    public const DEFAULT_RETENTION_DAYS = 30;

    public function runRetentionDays(): int
    {
        $stored = get_option(self::OPTION, []);
        $days = is_array($stored) && isset($stored['retention_days']) && is_numeric($stored['retention_days'])
            ? (int) $stored['retention_days']
            : self::DEFAULT_RETENTION_DAYS;

        /**
         * Filters how many days finished runs are kept. Zero keeps them forever.
         *
         * @param int $days Default 30.
         */
        return max(0, (int) apply_filters('agent_studio_run_retention_days', $days));
    }
}
