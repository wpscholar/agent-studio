<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Agent;

/**
 * Something that happened which may start one or more agents.
 *
 * The payload is data from the outside world and must be treated as untrusted.
 */
final class TriggerEvent
{
    /**
     * @param array<string, mixed> $payload
     * @param string               $dedupeKey    Events with the same key inside the window start an agent only once.
     * @param int                  $dedupeWindow Seconds.
     * @param int|null             $agentId      Set when the event targets exactly one agent (webhook, manual).
     */
    public function __construct(
        public readonly TriggerType $type,
        public readonly array $payload = [],
        public readonly string $dedupeKey = '',
        public readonly int $dedupeWindow = 60,
        public readonly ?int $agentId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function targeted(
        TriggerType $type,
        int $agentId,
        array $payload = [],
        string $dedupeKey = '',
        int $dedupeWindow = 86400
    ): self {
        return new self($type, $payload, $dedupeKey, $dedupeWindow, $agentId);
    }
}
