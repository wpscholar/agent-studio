<?php

declare(strict_types=1);

namespace WPAgents\WordPress;

use WPAgents\Infrastructure\PostTypes;

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
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=wpa-runs');
    }

    public static function run(int $runId): string
    {
        return add_query_arg('run', $runId, self::runs());
    }

    public static function abilities(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=wpa-abilities');
    }

    public static function templates(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=wpa-templates');
    }

    public static function importSkill(): string
    {
        return admin_url('edit.php?post_type=' . PostTypes::AGENT . '&page=wpa-import-skill');
    }

    public static function webhook(int $agentId): string
    {
        return rest_url('wp-agents/v1/webhooks/' . $agentId);
    }
}
