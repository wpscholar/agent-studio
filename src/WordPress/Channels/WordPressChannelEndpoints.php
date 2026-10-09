<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Channels;

use AgentStudio\Contracts\Channel\ChannelEndpoints;
use AgentStudio\Domain\Channel\Channel;
use AgentStudio\WordPress\Urls;

final class WordPressChannelEndpoints implements ChannelEndpoints
{
    public function inbound(Channel $channel): string
    {
        return rest_url('agent-studio/v1/channels/' . $channel->id . '/inbound');
    }

    public function run(int $runId): string
    {
        return Urls::run($runId);
    }

    public function approvalLink(int $runId, bool $approve, int $channelId, string $token): string
    {
        return add_query_arg(
            ['decision' => $approve ? 'approve' : 'reject', 'token' => $token],
            rest_url('agent-studio/v1/channels/' . $channelId . '/decide/' . $runId)
        );
    }

    public function authorizationReturn(): string
    {
        return admin_url('admin-post.php?action=agent_studio_channel_connected');
    }

    public function siteName(): string
    {
        return wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
    }
}
