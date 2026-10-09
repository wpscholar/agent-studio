<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Channel;

/**
 * Someone who has written to a channel, as that channel knows them.
 *
 * A contact can be linked to a WordPress user. Only linked people can approve
 * actions from inside the channel, and only if that user may approve them.
 */
final class Contact
{
    /**
     * @param string $externalId The channel's own id for the person (Telegram user id, Slack user id…).
     * @param string $address    Where to reach them directly (their private chat), if the channel has one.
     */
    public function __construct(
        public readonly int $id,
        public readonly int $channelId,
        public readonly string $externalId,
        public readonly string $name,
        public readonly ContactStatus $status = ContactStatus::Pending,
        public readonly int $userId = 0,
        public readonly string $address = '',
        public readonly int $createdAt = 0,
        public readonly int $lastSeenAt = 0,
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->status === ContactStatus::Allowed;
    }

    public function withStatus(ContactStatus $status): self
    {
        return new self($this->id, $this->channelId, $this->externalId, $this->name, $status, $this->userId, $this->address, $this->createdAt, $this->lastSeenAt);
    }

    public function linkedTo(int $userId): self
    {
        return new self($this->id, $this->channelId, $this->externalId, $this->name, ContactStatus::Allowed, $userId, $this->address, $this->createdAt, $this->lastSeenAt);
    }

    public function seen(string $name, string $address, int $now): self
    {
        return new self(
            $this->id,
            $this->channelId,
            $this->externalId,
            $name !== '' ? $name : $this->name,
            $this->status,
            $this->userId,
            $address !== '' ? $address : $this->address,
            $this->createdAt,
            $now
        );
    }
}
