<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Http;

final class HttpResponse
{
    /**
     * @param array<string, string> $headers Lowercase names.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * The body as a JSON object, or null when it is not one.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);

        return is_array($data) && ($data === [] || ! array_is_list($data)) ? $data : null;
    }
}
