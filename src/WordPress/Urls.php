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

    public static function importSkill(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=agent-studio-import-skill');
    }

    public static function webhook(int $agentId): string
    {
        return rest_url('agent-studio/v1/webhooks/' . $agentId);
    }
}
