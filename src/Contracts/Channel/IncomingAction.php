<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

/**
 * Someone pressed a button Agent Studio sent, such as Approve.
 */
final class IncomingAction
{
    /**
     * @param string $value        The button's value, as Agent Studio set it.
     * @param string $replyAddress Where to confirm what happened.
     * @param string $reference    The channel's handle for the press, if it must be acknowledged.
     */
    public function __construct(
        public readonly string $senderId,
        public readonly string $senderName,
        public readonly string $value,
        public readonly string $replyAddress,
        public readonly string $reference = '',
    ) {
    }
}
