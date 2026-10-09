<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

/**
 * A request a channel's service sent to this site, exactly as received, so the
 * channel type can check its signature before trusting anything in it.
 */
final class InboundRequest
{
    /**
     * @param array<string, string> $headers Lowercase names.
     * @param array<string, string> $query
     */
    public function __construct(
        public readonly array $headers,
        public readonly string $body,
        public readonly array $query = [],
    ) {
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : null;
    }
}
