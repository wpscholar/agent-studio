<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Model;

use AgentStudio\Domain\Conversation\ToolCall;

final class ModelResponse
{
    /**
     * @param list<ToolCall> $toolCalls
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls = [],
        public readonly int $tokensIn = 0,
        public readonly int $tokensOut = 0,
    ) {
    }
}
