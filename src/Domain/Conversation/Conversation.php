<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Conversation;

/**
 * An ongoing chat between people in a channel and an agent: a Telegram chat,
 * a Slack thread. It stays open until it goes quiet; older messages are
 * folded into a running summary so it never outgrows what a model can read.
 */
final class Conversation
{
    /**
     * @param string $threadKey          The channel's id for the chat or thread, so the next message finds this conversation.
     * @param string $address            Where replies go, in the channel's own terms.
     * @param int    $summarizedThrough  The last message folded into the summary (0 for none).
     */
    public function __construct(
        public readonly int $id,
        public readonly int $agentId,
        public readonly int $channelId,
        public readonly string $threadKey,
        public readonly string $address,
        public readonly string $summary = '',
        public readonly int $summarizedThrough = 0,
        public readonly bool $closed = false,
        public readonly int $startedAt = 0,
        public readonly int $lastActivityAt = 0,
    ) {
    }

    public function touched(int $now): self
    {
        return new self($this->id, $this->agentId, $this->channelId, $this->threadKey, $this->address, $this->summary, $this->summarizedThrough, $this->closed, $this->startedAt, $now);
    }

    public function close(): self
    {
        return new self($this->id, $this->agentId, $this->channelId, $this->threadKey, $this->address, $this->summary, $this->summarizedThrough, true, $this->startedAt, $this->lastActivityAt);
    }

    public function withSummary(string $summary, int $throughMessageId): self
    {
        return new self($this->id, $this->agentId, $this->channelId, $this->threadKey, $this->address, $summary, $throughMessageId, $this->closed, $this->startedAt, $this->lastActivityAt);
    }

    public function withId(int $id): self
    {
        return new self($id, $this->agentId, $this->channelId, $this->threadKey, $this->address, $this->summary, $this->summarizedThrough, $this->closed, $this->startedAt, $this->lastActivityAt);
    }
}
