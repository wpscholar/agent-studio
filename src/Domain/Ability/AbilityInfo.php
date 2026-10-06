<?php

declare(strict_types=1);

namespace WPAgents\Domain\Ability;

/**
 * A framework-neutral description of an ability an agent could use.
 */
final class AbilityInfo
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema for the input. Always an object schema or empty.
     * @param bool                 $inputWrapped True when the ability's own schema is not an object schema and was
     *                                           wrapped as {"input": <schema>} so models can call it.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $description,
        public readonly string $category,
        public readonly Risk $risk,
        public readonly array $inputSchema = [],
        public readonly bool $inputWrapped = false,
    ) {
    }

    public function toolName(): string
    {
        return ToolNames::forAbility($this->name);
    }
}
