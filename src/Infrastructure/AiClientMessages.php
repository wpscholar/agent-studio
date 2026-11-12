<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use stdClass;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use AgentStudio\Domain\Conversation\Message as ConversationMessage;
use AgentStudio\Domain\Conversation\ToolCall;

/**
 * Converts between our conversation messages and the WordPress AI Client's.
 *
 * Tool calls keep the model's signature both ways. Gemini thinking models
 * reject a conversation whose earlier function calls come back without the
 * thought signature they were sent with.
 */
final class AiClientMessages
{
    /**
     * @param list<ConversationMessage> $messages
     *
     * @return list<Message>
     */
    public function toHistory(array $messages): array
    {
        $history = [];

        foreach ($messages as $message) {
            $mapped = $this->toAiClient($message);
            if ($mapped !== null) {
                $history[] = $mapped;
            }
        }

        return $history;
    }

    public function toAiClient(ConversationMessage $message): ?Message
    {
        switch ($message->role) {
            case ConversationMessage::ROLE_USER:
                return $message->content === ''
                    ? null
                    : new Message(MessageRoleEnum::user(), [new MessagePart($message->content)]);

            case ConversationMessage::ROLE_ASSISTANT:
                $parts = [];
                if ($message->content !== '') {
                    $parts[] = new MessagePart($message->content);
                }

                foreach ($message->toolCalls as $call) {
                    // An empty argument map must reach the provider as {} and not [].
                    $args = $call->input === [] ? new stdClass() : $call->input;
                    $parts[] = new MessagePart(
                        new FunctionCall($call->id, $call->name, $args),
                        null,
                        $call->signature !== '' ? $call->signature : null
                    );
                }

                return $parts === [] ? null : new Message(MessageRoleEnum::model(), $parts);

            case ConversationMessage::ROLE_TOOL:
                return new Message(
                    MessageRoleEnum::user(),
                    [new MessagePart(new FunctionResponse($message->toolCallId, $message->toolName, $message->content))]
                );
        }

        return null;
    }

    /**
     * The model's reply: its text, and the tools it wants called.
     *
     * @return array{0: string, 1: list<ToolCall>}
     */
    public function fromAiClient(Message $message): array
    {
        $text = '';
        $toolCalls = [];

        foreach ($message->getParts() as $part) {
            $partText = $part->getText();
            if ($partText !== null) {
                $text .= $partText;
            }

            $call = $part->getFunctionCall();
            if ($call !== null) {
                $args = $call->getArgs();
                $toolCalls[] = new ToolCall(
                    (string) ($call->getId() ?? $call->getName()),
                    (string) $call->getName(),
                    is_array($args) ? $args : [],
                    (string) $part->getThoughtSignature()
                );
            }
        }

        return [$text, $toolCalls];
    }
}
