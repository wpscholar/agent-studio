<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Connection;

use AgentStudio\Domain\Ability\Risk;

/**
 * A tool another service offers, as last listed by that service.
 *
 * Its hints come from the other service and are not trusted to make anything
 * less safe: only a tool that says it only reads skips approval, and anything
 * that does not say what it does is treated as acting outside the site.
 */
final class RemoteTool
{
    /**
     * @param array<string, mixed> $inputSchema
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly array $inputSchema = [],
        public readonly bool $readOnly = false,
        public readonly bool $destructive = false,
    ) {
    }

    public function risk(): Risk
    {
        if ($this->readOnly) {
            return Risk::Read;
        }

        return $this->destructive ? Risk::Destructive : Risk::External;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'input_schema' => $this->inputSchema,
            'read_only' => $this->readOnly,
            'destructive' => $this->destructive,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        if ($name === '') {
            return null;
        }

        return new self(
            $name,
            is_string($data['title'] ?? null) ? $data['title'] : '',
            is_string($data['description'] ?? null) ? $data['description'] : '',
            is_array($data['input_schema'] ?? null) ? $data['input_schema'] : [],
            ($data['read_only'] ?? false) === true,
            ($data['destructive'] ?? false) === true
        );
    }
}
