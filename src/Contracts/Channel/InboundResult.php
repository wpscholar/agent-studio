<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

/**
 * What the channel type made of an inbound request: what to answer the service
 * right away, and what people did.
 */
final class InboundResult
{
    /**
     * @param list<IncomingMessage|IncomingAction> $events
     */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $body = '',
        public readonly string $contentType = 'text/plain',
        public readonly array $events = [],
    ) {
    }

    public static function rejected(): self
    {
        return new self(401, 'Invalid signature.');
    }

    /**
     * @param list<IncomingMessage|IncomingAction> $events
     */
    public static function accepted(array $events = []): self
    {
        return new self(200, 'ok', 'text/plain', $events);
    }
}
