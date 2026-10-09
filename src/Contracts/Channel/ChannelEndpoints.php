<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

use AgentStudio\Domain\Channel\Channel;

/**
 * Addresses on this site that channels need.
 */
interface ChannelEndpoints
{
    /** Where the channel's service sends messages and button presses. */
    public function inbound(Channel $channel): string;

    /** The run's page in the admin. */
    public function run(int $runId): string;

    /** A signed link that approves or rejects, for channels that can only call a URL. */
    public function approvalLink(int $runId, bool $approve, int $channelId, string $token): string;

    public function siteName(): string;
}
