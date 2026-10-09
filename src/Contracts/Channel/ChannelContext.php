<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

/**
 * What a channel type needs besides the channel itself: its saved secrets and
 * the addresses this site uses for it.
 */
final class ChannelContext
{
    /**
     * @param array<string, string> $secrets    Secret setup values by field key.
     * @param string                $inboundUrl Where the service should send messages and button presses.
     */
    public function __construct(
        public readonly array $secrets,
        public readonly string $inboundUrl,
        public readonly string $siteName,
    ) {
    }

    public function secret(string $key): string
    {
        return $this->secrets[$key] ?? '';
    }
}
