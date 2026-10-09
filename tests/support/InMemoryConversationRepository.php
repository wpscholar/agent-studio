<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\ConversationRepository;
use AgentStudio\Domain\Conversation\ChatMessage;
use AgentStudio\Domain\Conversation\Conversation;

final class InMemoryConversationRepository implements ConversationRepository
{
    /** @var array<int, Conversation> */
    public array $conversations = [];

    /** @var array<int, ChatMessage> */
    public array $messages = [];

    private int $nextId = 1;

    public function find(int $id): ?Conversation
    {
        return $this->conversations[$id] ?? null;
    }

    public function findOpen(int $channelId, string $threadKey): ?Conversation
    {
        foreach (array_reverse($this->conversations) as $conversation) {
            if ($conversation->channelId === $channelId && $conversation->threadKey === $threadKey && ! $conversation->closed) {
                return $conversation;
            }
        }

        return null;
    }

    public function save(Conversation $conversation): Conversation
    {
        if ($conversation->id === 0) {
            $conversation = $conversation->withId($this->nextId++);
        }

        return $this->conversations[$conversation->id] = $conversation;
    }

    public function addMessage(ChatMessage $message): ChatMessage
    {
        $message = $message->withId($this->nextId++);

        return $this->messages[$message->id] = $message;
    }

    public function messagesAfter(int $conversationId, int $afterMessageId): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (ChatMessage $m): bool => $m->conversationId === $conversationId && $m->id > $afterMessageId
        ));
    }

    public function deleteForChannel(int $channelId): void
    {
    }
}
