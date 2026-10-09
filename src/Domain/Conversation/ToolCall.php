<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Conversation;

/**
 * A request from the model to call one tool.
 */
final class ToolCall
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $input = [],
    ) {
    }

    /**
     * @return array{id: string, name: string, input: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'input' => $this->input];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $input = $data['input'] ?? [];

        return new self(
            is_scalar($data['id'] ?? null) ? (string) $data['id'] : '',
            is_scalar($data['name'] ?? null) ? (string) $data['name'] : '',
            is_array($input) ? $input : [],
        );
    }
}
