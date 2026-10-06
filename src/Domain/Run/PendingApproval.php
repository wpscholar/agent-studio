<?php

declare(strict_types=1);

namespace WPAgents\Domain\Run;

use WPAgents\Domain\Ability\Risk;

/**
 * A tool call that is waiting for a person to approve or reject it.
 */
final class PendingApproval
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $toolName,
        public readonly string $abilityName,
        public readonly array $input,
        public readonly Risk $risk,
        public readonly int $requestedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool_call_id' => $this->toolCallId,
            'tool_name' => $this->toolName,
            'ability' => $this->abilityName,
            'input' => $this->input,
            'risk' => $this->risk->value,
            'requested_at' => $this->requestedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $input = $data['input'] ?? [];

        return new self(
            is_scalar($data['tool_call_id'] ?? null) ? (string) $data['tool_call_id'] : '',
            is_scalar($data['tool_name'] ?? null) ? (string) $data['tool_name'] : '',
            is_scalar($data['ability'] ?? null) ? (string) $data['ability'] : '',
            is_array($input) ? $input : [],
            Risk::fromString(is_scalar($data['risk'] ?? null) ? (string) $data['risk'] : ''),
            is_numeric($data['requested_at'] ?? null) ? (int) $data['requested_at'] : 0,
        );
    }
}
