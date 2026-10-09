<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use AgentStudio\Infrastructure\PostTypes;

final class Urls
{
    public static function agents(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT);
    }

    public static function editAgent(int $agentId): string
    {
        return admin_url('post.php?post=' . $agentId . '&action=edit');
    }

    public static function runs(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=agent-studio-runs');
    }

    public static function run(int $runId): string
    {
        return add_query_arg('run', $runId, self::runs());
    }

    public static function abilities(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=agent-studio-abilities');
    }

    public static function templates(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=agent-studio-templates');
    }

    public static function skills(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=agent-studio-skills');
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
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=agent-studio-connections');
    }

    public static function connection(int $connectionId): string
    {
        return add_query_arg('connection', $connectionId, self::connections());
    }

    public static function channels(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=agent-studio-channels');
    }

    public static function channel(int $channelId): string
    {
        return add_query_arg('channel', $channelId, self::channels());
    }

    public static function webhook(int $agentId): string
    {
        return rest_url('agent-studio/v1/webhooks/' . $agentId);
    }
}
