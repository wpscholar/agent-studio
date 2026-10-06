<?php

declare(strict_types=1);

namespace WPAgents\Contracts\Model;

/**
 * A tool the model may call.
 */
final class ToolSpec
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema for the arguments.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
    ) {
    }
}
