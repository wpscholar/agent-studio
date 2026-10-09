<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\Http\HttpClient;
use AgentStudio\Contracts\Http\HttpException;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Contracts\Http\HttpResponse;

/**
 * Answers requests from a handler, and records them.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var callable(HttpRequest): (HttpResponse|null) */
    private $handler;

    /**
     * @param callable(HttpRequest): (HttpResponse|null) $handler Return null to simulate an unreachable host.
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $response = ($this->handler)($request);
        if ($response === null) {
            throw new HttpException('Could not connect.');
        }

        return $response;
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): HttpResponse
    {
        return new HttpResponse($status, ['content-type' => 'application/json'] + $headers, (string) json_encode($data));
    }

    /**
     * The JSON body of a request.
     *
     * @return array<string, mixed>
     */
    public static function body(HttpRequest $request): array
    {
        $data = json_decode($request->body, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @return list<HttpRequest>
     */
    public function to(string $urlPrefix): array
    {
        return array_values(array_filter($this->requests, static fn (HttpRequest $r): bool => str_starts_with($r->url, $urlPrefix)));
    }
}
