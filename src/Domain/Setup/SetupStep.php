<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Setup;

/**
 * One numbered step of a setup: what to do, in plain words, and any fields to fill in.
 */
final class SetupStep
{
    /**
     * @param list<string>     $instructions Short paragraphs. URLs in them become links.
     * @param list<SetupField> $fields
     */
    public function __construct(
        public readonly string $title,
        public readonly array $instructions = [],
        public readonly array $fields = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'instructions' => $this->instructions,
            'fields' => array_map(static fn (SetupField $field): array => $field->toArray(), $this->fields),
        ];
    }
}
