<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Channel;

/**
 * What a kind of channel can do. The screens use this to show only what makes sense.
 */
final class ChannelCapabilities
{
    /**
     * @param bool $receive People can send the agent messages.
     * @param bool $reply   The agent can answer in the same place.
     * @param bool $notify  Agent Studio can send notifications there.
     * @param bool $approve Approve and Reject buttons work there.
     */
    public function __construct(
        public readonly bool $receive = false,
        public readonly bool $reply = false,
        public readonly bool $notify = false,
        public readonly bool $approve = false,
    ) {
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return ['receive' => $this->receive, 'reply' => $this->reply, 'notify' => $this->notify, 'approve' => $this->approve];
    }
}
