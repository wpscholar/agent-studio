<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Conversation;

/**
 * How much of a conversation the agent sees.
 *
 * The newest messages are shown word for word, up to a count and a size.
 * Anything older is folded into a running summary. A conversation that has
 * been quiet for a day is closed, and the next message starts a new one.
 */
final class ConversationWindow
{
    public const MAX_MESSAGES = 20;
    public const MAX_CHARS = 24000;
    public const IDLE_SECONDS = 86400;

    public function __construct(
        public readonly int $maxMessages = self::MAX_MESSAGES,
        public readonly int $maxChars = self::MAX_CHARS,
        public readonly int $idleSeconds = self::IDLE_SECONDS,
    ) {
    }

    public function isIdle(Conversation $conversation, int $now): bool
    {
        return $conversation->closed || $now - $conversation->lastActivityAt > $this->idleSeconds;
    }

    /**
     * Splits the messages not yet in the summary (oldest first) into the ones
     * shown word for word and the older ones to fold into the summary.
     * The newest message is always shown, even when it is long.
     *
     * @param list<ChatMessage> $messages
     *
     * @return array{window: list<ChatMessage>, overflow: list<ChatMessage>}
     */
    public function split(array $messages): array
    {
        $window = [];
        $chars = 0;

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $length = mb_strlen($messages[$i]->text);
            if ($window !== [] && (count($window) >= $this->maxMessages || $chars + $length > $this->maxChars)) {
                break;
            }
            array_unshift($window, $messages[$i]);
            $chars += $length;
        }

        return ['window' => $window, 'overflow' => array_slice($messages, 0, count($messages) - count($window))];
    }
}
