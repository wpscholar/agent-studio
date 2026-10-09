<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

use AgentStudio\Domain\Channel\Channel;
use AgentStudio\Domain\Setup\SetupStep;

/**
 * For channel types whose setup needs the channel to exist first, for example
 * because the other service must be given this channel's own addresses.
 *
 * The Add screen saves the name and agent, then the channel's page shows these
 * steps (with links and buttons) until the channel is switched on.
 */
interface GuidedSetup
{
    /**
     * @param string $inboundUrl  Where the service sends messages for this channel.
     * @param string $redirectUrl Where the service sends people back after they connect it.
     *
     * @return list<SetupStep>
     */
    public function followUpSteps(Channel $channel, string $inboundUrl, string $redirectUrl): array;
}
