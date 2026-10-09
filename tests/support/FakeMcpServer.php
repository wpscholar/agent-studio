<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Contracts\Http\HttpResponse;

/**
 * A tiny MCP server speaking the current protocol, for tests. Optionally
 * requires a bearer token and can answer as an event stream.
 */
final class FakeMcpServer
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public bool $stream = false;

    /**
     * @param list<array<string, mixed>> $tools
     */
    public function __construct(
        public array $tools = [],
        public ?string $token = null,
        public string $challenge = 'Bearer resource_metadata="https://mcp.example.com/.well-known/oauth-protected-resource/mcp"',
    ) {
        if ($this->tools === []) {
            $this->tools = [
                ['name' => 'search_issues', 'title' => 'Search issues', 'description' => 'Find issues.', 'inputSchema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]], 'annotations' => ['readOnlyHint' => true]],
                ['name' => 'create_issue', 'description' => 'Create an issue.', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]]],
                ['name' => 'delete_repo', 'description' => 'Delete a repository.', 'annotations' => ['destructiveHint' => true]],
            ];
        }
    }

    public function handle(HttpRequest $request): ?HttpResponse
    {
        if ($this->token !== null && ($request->headers['Authorization'] ?? '') !== 'Bearer ' . $this->token) {
            return new HttpResponse(401, ['www-authenticate' => $this->challenge], '');
        }

        $body = FakeHttpClient::body($request);
        $id = $body['id'] ?? null;
        $method = $body['method'] ?? '';
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];

        $result = match ($method) {
            'tools/list' => ['tools' => $this->tools],
            'tools/call' => $this->call($params),
            default => null,
        };

        $message = $result === null
            ? ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32601, 'message' => 'Method not found']]
            : ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];

        if ($this->stream) {
            return new HttpResponse(200, ['content-type' => 'text/event-stream'], "event: message\ndata: " . json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/progress', 'params' => []]) . "\n\ndata: " . json_encode($message) . "\n\n");
        }

        return FakeHttpClient::json($message);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function call(array $params): array
    {
        $this->calls[] = $params;
        if (($params['name'] ?? '') === 'delete_repo') {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Not allowed.']]];
        }

        return ['content' => [['type' => 'text', 'text' => 'Done: ' . json_encode($params['arguments'] ?? [])]]];
    }
}
