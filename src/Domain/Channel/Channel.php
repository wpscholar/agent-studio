<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Channel;

use InvalidArgumentException;

/**
 * A place outside WordPress where people talk to an agent, or where Agent
 * Studio sends notifications: a Telegram bot, an ntfy topic, a Slack app.
 */
final class Channel
{
    /**
     * @param array<string, string> $settings  Non-secret setup values. Types may add their own (like a bot's username).
     * @param int                   $agentId   The agent people talk to here. 0 for notification-only channels.
     * @param int                   $ownerId   The person who set it up. Notifications here are theirs.
     * @param string                $notifyTo  Where notifications go inside the channel (for example the owner's chat). Empty: the type's default.
     * @param bool                  $notify    Send approval requests and problems here.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $name,
        public readonly int $agentId = 0,
        public readonly int $ownerId = 0,
        public readonly array $settings = [],
        public readonly ChannelStatus $status = ChannelStatus::New,
        public readonly string $message = '',
        public readonly string $notifyTo = '',
        public readonly bool $notify = true,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('A channel needs a name.');
        }
    }

    public function setting(string $key): string
    {
        return $this->settings[$key] ?? '';
    }

    public function isActive(): bool
    {
        return $this->status === ChannelStatus::Active;
    }

    /**
     * A copy with some properties changed.
     *
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): self
    {
        $values = get_object_vars($this);
        foreach ($changes as $key => $value) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException(sprintf('Channel has no property "%s".', $key));
            }
            $values[$key] = $value;
        }

        /** @var array{id: int, type: string, name: string, agentId: int, ownerId: int, settings: array<string, string>, status: ChannelStatus, message: string, notifyTo: string, notify: bool} $values */
        return new self(...$values);
    }
}
