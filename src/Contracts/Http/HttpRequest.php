<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Http;

final class HttpRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly string $body = '',
        public readonly int $timeout = 30,
    ) {
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    public static function json(string $url, array $data, array $headers = [], int $timeout = 30): self
    {
        return new self(
            'POST',
            $url,
            ['Content-Type' => 'application/json'] + $headers,
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            $timeout
        );
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $headers
     */
    public static function form(string $url, array $fields, array $headers = []): self
    {
        return new self(
            'POST',
            $url,
            ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'] + $headers,
            http_build_query($fields, '', '&', PHP_QUERY_RFC1738)
        );
    }
}
