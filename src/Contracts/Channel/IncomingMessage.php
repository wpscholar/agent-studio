<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

/**
 * Someone wrote to the agent.
 */
final class IncomingMessage
{
    /**
     * @param string $messageId     The channel's id for the message, so retries are not answered twice.
     * @param string $senderId      The channel's id for the person.
     * @param string $threadKey     Identifies the chat or thread, so the conversation continues there.
     * @param string $replyAddress  Where the answer goes, in the channel's terms.
     * @param string $directAddress The person's private chat, if the channel has one (for notifications).
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $senderId,
        public readonly string $senderName,
        public readonly string $text,
        public readonly string $threadKey,
        public readonly string $replyAddress,
        public readonly string $directAddress = '',
    ) {
    }
}
