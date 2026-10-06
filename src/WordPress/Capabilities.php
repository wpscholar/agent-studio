<?php

declare(strict_types=1);

namespace WPAgents\WordPress;

final class Capabilities
{
    /**
     * The capability needed to manage agents, skills and runs.
     *
     * Agents can change content and send email, so this defaults to the
     * administrator capability.
     */
    public static function manage(): string
    {
        /**
         * Filters the capability required to manage WP Agents.
         *
         * @param string $capability Default "manage_options".
         */
        return (string) apply_filters('wpa_manage_capability', 'manage_options');
    }

    public static function canManage(): bool
    {
        return current_user_can(self::manage());
    }
}
