<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use AgentStudio\WordPress\Admin\Menu;

final class Urls
{
    public static function agents(): string
    {
        return self::page(Menu::AGENTS);
    }

    public static function editAgent(int $agentId): string
    {
        return add_query_arg('agent', $agentId, self::agents());
    }

    /**
     * @param string $template Optionally, a template to start from.
     */
    public static function newAgent(string $template = ''): string
    {
        return add_query_arg('agent', $template === '' ? 'new' : 'new:' . rawurlencode($template), self::agents());
    }

    public static function runs(): string
    {
        return self::page(Menu::RUNS);
    }

    public static function run(int $runId): string
    {
        return add_query_arg('run', $runId, self::runs());
    }

    public static function abilities(): string
    {
        return self::page(Menu::ABILITIES);
    }

    public static function skills(): string
    {
        return self::page(Menu::SKILLS);
    }

    /**
     * @param string $view "new", "import", or a skill slug to open it.
     */
    public static function skill(string $view): string
    {
        return add_query_arg('skill', rawurlencode($view), self::skills());
    }

    public static function connections(): string
    {
        return self::page(Menu::CONNECTIONS);
    }

    public static function connection(int $connectionId): string
    {
        return add_query_arg('connection', $connectionId, self::connections());
    }

    public static function channels(): string
    {
        return self::page(Menu::CHANNELS);
    }

    public static function channel(int $channelId): string
    {
        return add_query_arg('channel', $channelId, self::channels());
    }

    public static function webhook(int $agentId): string
    {
        return rest_url('agent-studio/v1/webhooks/' . $agentId);
    }

    private static function page(string $slug): string
    {
        return admin_url('admin.php?page=' . $slug);
    }
}
