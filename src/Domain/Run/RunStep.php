<?php

declare(strict_types=1);

namespace WPAgents\Domain\Run;

/**
 * One line of the run log.
 */
final class RunStep
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly StepKind $kind,
        public readonly string $label,
        public readonly array $data,
        public readonly int $at,
    ) {
    }

    /**
     * @return array{kind: string, label: string, data: array<string, mixed>, at: int}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'label' => $this->label, 'data' => $this->data, 'at' => $this->at];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $payload = $data['data'] ?? [];

        return new self(
            StepKind::tryFrom(is_string($data['kind'] ?? null) ? $data['kind'] : '') ?? StepKind::Note,
            is_scalar($data['label'] ?? null) ? (string) $data['label'] : '',
            is_array($payload) ? $payload : [],
            is_numeric($data['at'] ?? null) ? (int) $data['at'] : 0,
        );
    }
}
