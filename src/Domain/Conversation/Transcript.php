<?php

declare(strict_types=1);

namespace WPAgents\Domain\Conversation;

/**
 * Helpers for reading a conversation.
 */
final class Transcript
{
    /**
     * Tool calls from the latest assistant message that have no result yet.
     *
     * This is how a run that paused for approval knows where to pick up.
     *
     * @param list<Message> $messages
     *
     * @return list<ToolCall>
     */
    public static function unansweredCalls(array $messages): array
    {
        $lastAssistantIndex = null;
        foreach ($messages as $index => $message) {
            if ($message->role === Message::ROLE_ASSISTANT) {
                $lastAssistantIndex = $index;
            }
        }

        if ($lastAssistantIndex === null) {
            return [];
        }

        $calls = $messages[$lastAssistantIndex]->toolCalls;
        if ($calls === []) {
            return [];
        }

        $answered = [];
        foreach (array_slice($messages, $lastAssistantIndex + 1) as $message) {
            if ($message->role === Message::ROLE_TOOL) {
                $answered[$message->toolCallId] = true;
            }
        }

        return array_values(array_filter(
            $calls,
            static fn (ToolCall $call): bool => ! isset($answered[$call->id])
        ));
    }
}
