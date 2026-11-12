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
     * @param string               $signature An opaque token some models attach to a call (Gemini's
     *                                        "thought signature"). It must go back to the model
     *                                        unchanged with the rest of the conversation, or the
     *                                        model rejects the next turn.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $input = [],
        public readonly string $signature = '',
    ) {
    }

    /**
     * @return array{id: string, name: string, input: array<string, mixed>, signature?: string}
     */
    public function toArray(): array
    {
        $data = ['id' => $this->id, 'name' => $this->name, 'input' => $this->input];
        if ($this->signature !== '') {
            $data['signature'] = $this->signature;
        }

        return $data;
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
            is_string($data['signature'] ?? null) ? $data['signature'] : '',
        );
    }
}
