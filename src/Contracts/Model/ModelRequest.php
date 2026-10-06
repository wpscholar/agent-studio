<?php

declare(strict_types=1);

namespace WPAgents\Contracts\Model;

use WPAgents\Domain\Conversation\Message;

final class ModelRequest
{
    /**
     * @param list<Message>  $messages
     * @param list<ToolSpec> $tools
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly array $messages,
        public readonly array $tools,
        public readonly string $model = '',
        public readonly int $maxTokens = 4096,
    ) {
    }
}
