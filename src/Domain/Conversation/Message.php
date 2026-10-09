<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Conversation;

/**
 * One turn in an agent's conversation with the model.
 */
final class Message
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    /**
     * @param list<ToolCall> $toolCalls Tool calls requested by an assistant message.
     */
    private function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly string $toolCallId = '',
        public readonly string $toolName = '',
    ) {
    }

    public static function user(string $content): self
    {
        return new self(self::ROLE_USER, $content);
    }

    /**
     * @param list<ToolCall> $toolCalls
     */
    public static function assistant(string $content, array $toolCalls = []): self
    {
        return new self(self::ROLE_ASSISTANT, $content, $toolCalls);
    }

    /**
     * The result of a tool call, sent back to the model.
     */
    public static function tool(string $toolCallId, string $toolName, string $content): self
    {
        return new self(self::ROLE_TOOL, $content, [], $toolCallId, $toolName);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'tool_calls' => array_map(static fn (ToolCall $call): array => $call->toArray(), $this->toolCalls),
            'tool_call_id' => $this->toolCallId,
            'tool_name' => $this->toolName,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $role = is_string($data['role'] ?? null) ? $data['role'] : self::ROLE_USER;
        $calls = [];
        $rawCalls = $data['tool_calls'] ?? [];
        if (is_array($rawCalls)) {
            foreach ($rawCalls as $rawCall) {
                if (is_array($rawCall)) {
                    $calls[] = ToolCall::fromArray($rawCall);
                }
            }
        }

        return new self(
            $role,
            is_scalar($data['content'] ?? null) ? (string) $data['content'] : '',
            $calls,
            is_scalar($data['tool_call_id'] ?? null) ? (string) $data['tool_call_id'] : '',
            is_scalar($data['tool_name'] ?? null) ? (string) $data['tool_name'] : '',
        );
    }
}
