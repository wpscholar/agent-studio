<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Conversation;

/**
 * One message in a conversation, from a person or from the agent.
 */
final class ChatMessage
{
    public const FROM_PERSON = 'person';
    public const FROM_AGENT = 'agent';

    public function __construct(
        public readonly int $id,
        public readonly int $conversationId,
        public readonly string $from,
        public readonly string $text,
        public readonly int $contactId = 0,
        public readonly int $runId = 0,
        public readonly int $createdAt = 0,
        public readonly string $senderName = '',
    ) {
    }

    public function isFromAgent(): bool
    {
        return $this->from === self::FROM_AGENT;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->conversationId, $this->from, $this->text, $this->contactId, $this->runId, $this->createdAt, $this->senderName);
    }
}
