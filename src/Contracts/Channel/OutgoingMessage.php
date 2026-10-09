<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

/**
 * Something Agent Studio sends through a channel: a reply, or a notification.
 */
final class OutgoingMessage
{
    /**
     * @param string              $to      Address in the channel's terms. Empty: the channel's default (for notifications).
     * @param list<MessageButton> $buttons
     */
    public function __construct(
        public readonly string $to,
        public readonly string $text,
        public readonly string $title = '',
        public readonly array $buttons = [],
        public readonly string $link = '',
    ) {
    }
}
