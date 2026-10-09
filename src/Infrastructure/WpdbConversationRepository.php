<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Contracts\ConversationRepository;
use AgentStudio\Domain\Conversation\ChatMessage;
use AgentStudio\Domain\Conversation\Conversation;

final class WpdbConversationRepository implements ConversationRepository
{
    public function find(int $id): ?Conversation
    {
        global $wpdb;

        $table = Schema::conversations();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? self::conversation($row) : null;
    }

    public function findOpen(int $channelId, string $threadKey): ?Conversation
    {
        global $wpdb;

        $table = Schema::conversations();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE channel_id = %d AND thread_key = %s AND closed = 0 ORDER BY id DESC LIMIT 1", $channelId, $threadKey),
            ARRAY_A
        );

        return is_array($row) ? self::conversation($row) : null;
    }

    public function save(Conversation $conversation): Conversation
    {
        global $wpdb;

        $row = [
            'agent_id' => $conversation->agentId,
            'channel_id' => $conversation->channelId,
            'thread_key' => $conversation->threadKey,
            'address' => $conversation->address,
            'summary' => $conversation->summary,
            'summarized_through' => $conversation->summarizedThrough,
            'closed' => $conversation->closed ? 1 : 0,
            'started_at' => $conversation->startedAt,
            'last_activity_at' => $conversation->lastActivityAt,
        ];

        if ($conversation->id > 0) {
            $wpdb->update(Schema::conversations(), $row, ['id' => $conversation->id]);

            return $conversation;
        }

        $wpdb->insert(Schema::conversations(), $row);

        return $conversation->withId((int) $wpdb->insert_id);
    }

    public function addMessage(ChatMessage $message): ChatMessage
    {
        global $wpdb;

        $wpdb->insert(Schema::messages(), [
            'conversation_id' => $message->conversationId,
            'sender' => $message->from,
            'sender_name' => mb_substr($message->senderName, 0, 191),
            'text' => $message->text,
            'contact_id' => $message->contactId,
            'run_id' => $message->runId,
            'created_at' => $message->createdAt,
        ]);

        return $message->withId((int) $wpdb->insert_id);
    }

    public function messagesAfter(int $conversationId, int $afterMessageId): array
    {
        global $wpdb;

        $table = Schema::messages();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE conversation_id = %d AND id > %d ORDER BY id ASC", $conversationId, $afterMessageId),
            ARRAY_A
        );

        $messages = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $messages[] = new ChatMessage(
                (int) $row['id'],
                (int) $row['conversation_id'],
                (string) $row['sender'],
                (string) $row['text'],
                (int) $row['contact_id'],
                (int) $row['run_id'],
                (int) $row['created_at'],
                (string) $row['sender_name']
            );
        }

        return $messages;
    }

    public function deleteForChannel(int $channelId): void
    {
        global $wpdb;

        $conversations = Schema::conversations();
        $messages = Schema::messages();
        $wpdb->query($wpdb->prepare("DELETE m FROM {$messages} m INNER JOIN {$conversations} c ON c.id = m.conversation_id WHERE c.channel_id = %d", $channelId));
        $wpdb->delete($conversations, ['channel_id' => $channelId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function conversation(array $row): Conversation
    {
        return new Conversation(
            (int) $row['id'],
            (int) $row['agent_id'],
            (int) $row['channel_id'],
            (string) $row['thread_key'],
            (string) $row['address'],
            (string) $row['summary'],
            (int) $row['summarized_through'],
            (int) $row['closed'] === 1,
            (int) $row['started_at'],
            (int) $row['last_activity_at']
        );
    }
}
