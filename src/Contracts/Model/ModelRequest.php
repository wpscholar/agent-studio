<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Model;

use AgentStudio\Domain\Conversation\Message;

final class ModelRequest
{
    /**
     * @param list<Message>  $messages
     * @param list<ToolSpec> $tools
     * @param string         $model    A model ID, or empty to let the AI Client choose one that can use the tools.
     * @param string         $provider The provider that serves $model. Empty means any provider.
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly array $messages,
        public readonly array $tools,
        public readonly string $model = '',
        public readonly int $maxTokens = 4096,
        public readonly string $provider = '',
    ) {
    }
}
