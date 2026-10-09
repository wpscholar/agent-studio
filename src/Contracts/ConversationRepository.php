<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

use AgentStudio\Domain\Conversation\ChatMessage;
use AgentStudio\Domain\Conversation\Conversation;

interface ConversationRepository
{
    public function find(int $id): ?Conversation;

    /**
     * The newest conversation that is not closed in this chat or thread.
     */
    public function findOpen(int $channelId, string $threadKey): ?Conversation;

    public function save(Conversation $conversation): Conversation;

    public function addMessage(ChatMessage $message): ChatMessage;

    /**
     * Messages after the given id, oldest first.
     *
     * @return list<ChatMessage>
     */
    public function messagesAfter(int $conversationId, int $afterMessageId): array;

    public function deleteForChannel(int $channelId): void;
}
