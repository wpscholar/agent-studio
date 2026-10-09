<?php

declare(strict_types=1);

namespace AgentStudio\Application\Mcp;

use AgentStudio\Contracts\Connection\AuthRequired;
use AgentStudio\Contracts\Http\HttpClient;
use AgentStudio\Contracts\Http\HttpException;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Contracts\Http\HttpResponse;
use AgentStudio\Domain\Connection\RemoteTool;

/**
 * A small MCP client for remote servers over Streamable HTTP.
 *
 * Speaks the current protocol (2026-07-28: one self-contained POST per request)
 * and falls back to the older session handshake (2025-11-25 and earlier) for
 * servers that do not understand it. Only what agents need: list tools, call a tool.
 */
final class McpClient
{
    public const PROTOCOL = '2026-07-28';
    public const LEGACY_PROTOCOL = '2025-11-25';

    private const MAX_PAGES = 20;
    private const MAX_TOOLS = 500;
    private const UNSUPPORTED_VERSION = -32022;
    private const HEADER_MISMATCH = -32020;

    /** Errors only a current server sends; anything else on a 4xx means an older server. */
    private const MODERN_ERRORS = [-32020, -32021, -32022, -32601, -32602];

    private int $nextId = 1;

    /** @var array<string, bool> Server URLs known to need the older handshake. */
    private array $legacy = [];

    public function __construct(
        private readonly HttpClient $http,
        private readonly string $clientName = 'Agent Studio',
        private readonly string $clientVersion = '1.0',
    ) {
    }

    /**
     * @param array<string, string> $headers Credentials to send.
     *
     * @return list<RemoteTool>
     *
     * @throws McpException
     * @throws AuthRequired
     */
    public function listTools(string $url, array $headers = []): array
    {
        $tools = [];
        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $this->request($url, $headers, 'tools/list', $cursor === null ? [] : ['cursor' => $cursor]);

            foreach (is_array($result['tools'] ?? null) ? $result['tools'] : [] as $raw) {
                $tool = is_array($raw) ? self::tool($raw) : null;
                if ($tool !== null && count($tools) < self::MAX_TOOLS) {
                    $tools[] = $tool;
                }
            }

            $cursor = is_string($result['nextCursor'] ?? null) && $result['nextCursor'] !== '' ? $result['nextCursor'] : null;
            if ($cursor === null) {
                break;
            }
        }

        return $tools;
    }

    /**
     * Calls a tool. A tool that ran but reported a problem comes back with
     * isError true; only failures to get an answer throw.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>  $arguments
     * @param array<string, mixed>  $inputSchema Used to send arguments the server wants as headers.
     *
     * @return array{isError: bool, text: string, structured: mixed}
     *
     * @throws McpException
     */
    public function callTool(string $url, array $headers, string $name, array $arguments, array $inputSchema = []): array
    {
        $result = $this->request(
            $url,
            $headers + self::parameterHeaders($inputSchema, $arguments),
            'tools/call',
            ['name' => $name, 'arguments' => (object) $arguments]
        );

        return [
            'isError' => ($result['isError'] ?? false) === true,
            'text' => self::contentText(is_array($result['content'] ?? null) ? $result['content'] : []),
            'structured' => $result['structuredContent'] ?? null,
        ];
    }

    /**
     * Whether the last exchange with this server used the older handshake.
     */
    public function usedLegacy(string $url): bool
    {
        return $this->legacy[$url] ?? false;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $params
     *
     * @return array<string, mixed>
     */
    private function request(string $url, array $headers, string $method, array $params): array
    {
        if ($this->legacy[$url] ?? false) {
            return $this->legacyRequest($url, $headers, $method, $params);
        }

        $id = $this->nextId++;
        $params['_meta'] = [
            'io.modelcontextprotocol/protocolVersion' => self::PROTOCOL,
            'io.modelcontextprotocol/clientCapabilities' => (object) [],
            'io.modelcontextprotocol/clientInfo' => ['name' => $this->clientName, 'version' => $this->clientVersion],
        ];

        $requestHeaders = $headers + [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => self::PROTOCOL,
            'Mcp-Method' => $method,
        ];
        if (is_string($params['name'] ?? null)) {
            $requestHeaders['Mcp-Name'] = self::headerValue($params['name']);
        }

        $response = $this->post($url, $requestHeaders, ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
        $message = self::find($response, $id);

        if ($message === null) {
            if ($response->status >= 400 && $response->status < 500) {
                // Not a modern server: it did not answer in JSON-RPC at all.
                $this->legacy[$url] = true;

                return $this->legacyRequest($url, $headers, $method, $params);
            }

            throw new McpException(self::httpProblem($response));
        }

        $error = $message['error'] ?? null;
        if (is_array($error)) {
            $code = (int) ($error['code'] ?? 0);
            $olderServer = ($message['id'] ?? null) === null
                || ($response->status >= 400 && $response->status < 500 && ! in_array($code, self::MODERN_ERRORS, true));
            if ($code === self::UNSUPPORTED_VERSION || $olderServer) {
                $this->legacy[$url] = true;

                return $this->legacyRequest($url, $headers, $method, $params);
            }
            if ($code === self::HEADER_MISMATCH) {
                throw new McpException('The service rejected the request because its tool list changed. Refresh the connection and try again.');
            }

            throw new McpException(self::errorText($error));
        }

        return self::result($message);
    }

    /**
     * The 2025 handshake: initialize, confirm, then the request, all in one session.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>  $params
     *
     * @return array<string, mixed>
     */
    private function legacyRequest(string $url, array $headers, string $method, array $params): array
    {
        unset($params['_meta']);
        $base = $headers + ['Accept' => 'application/json, text/event-stream'];

        $initId = $this->nextId++;
        $init = $this->post($url, $base, [
            'jsonrpc' => '2.0',
            'id' => $initId,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::LEGACY_PROTOCOL,
                'capabilities' => (object) [],
                'clientInfo' => ['name' => $this->clientName, 'version' => $this->clientVersion],
            ],
        ]);

        $message = self::find($init, $initId);
        if ($message === null) {
            throw new McpException(self::httpProblem($init));
        }
        if (is_array($message['error'] ?? null)) {
            throw new McpException(self::errorText($message['error']));
        }

        $result = self::result($message);
        $version = is_string($result['protocolVersion'] ?? null) ? $result['protocolVersion'] : self::LEGACY_PROTOCOL;
        $session = $init->header('mcp-session-id');

        $sessionHeaders = $base + ['MCP-Protocol-Version' => $version];
        if ($session !== '') {
            $sessionHeaders['Mcp-Session-Id'] = $session;
        }

        $this->post($url, $sessionHeaders, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        $id = $this->nextId++;
        $response = $this->post($url, $sessionHeaders, ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params]);

        if ($session !== '') {
            try {
                $this->http->send(new HttpRequest('DELETE', $url, $sessionHeaders, '', 10));
            } catch (HttpException) {
                // Ending the session is a courtesy.
            }
        }

        $message = self::find($response, $id);
        if ($message === null) {
            throw new McpException(self::httpProblem($response));
        }
        if (is_array($message['error'] ?? null)) {
            throw new McpException(self::errorText($message['error']));
        }

        return self::result($message);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $body
     */
    private function post(string $url, array $headers, array $body): HttpResponse
    {
        try {
            $response = $this->http->send(HttpRequest::json($url, $body, $headers, 60));
        } catch (HttpException $e) {
            throw new McpException('The service could not be reached: ' . $e->getMessage(), 0, $e);
        }

        if ($response->status === 401) {
            throw new AuthRequired($response->header('www-authenticate'));
        }
        if ($response->status === 403 && str_contains($response->header('www-authenticate'), 'insufficient_scope')) {
            throw new AuthRequired($response->header('www-authenticate'), 'The service needs more permissions. Sign in again to grant them.');
        }

        return $response;
    }

    /**
     * The JSON-RPC message with this id, from a JSON or event-stream body.
     *
     * @return array<string, mixed>|null
     */
    private static function find(HttpResponse $response, int $id): ?array
    {
        $type = strtolower($response->header('content-type'));
        $messages = str_contains($type, 'text/event-stream')
            ? SseParser::messages($response->body)
            : array_filter([$response->json()]);

        foreach ($messages as $message) {
            if (($message['jsonrpc'] ?? null) === '2.0' && ($message['id'] ?? null) === $id) {
                return $message;
            }
        }

        // Some servers answer errors without echoing the id.
        foreach ($messages as $message) {
            if (($message['jsonrpc'] ?? null) === '2.0' && array_key_exists('error', $message) && ($message['id'] ?? null) === null) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private static function result(array $message): array
    {
        $result = is_array($message['result'] ?? null) ? $message['result'] : [];

        if (($result['resultType'] ?? 'complete') === 'input_required') {
            throw new McpException('The service asked a follow-up question, which agents cannot answer yet.');
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function tool(array $raw): ?RemoteTool
    {
        $name = is_string($raw['name'] ?? null) ? $raw['name'] : '';
        if ($name === '' || strlen($name) > 128) {
            return null;
        }

        $annotations = is_array($raw['annotations'] ?? null) ? $raw['annotations'] : [];
        $title = is_string($raw['title'] ?? null) ? $raw['title'] : (is_string($annotations['title'] ?? null) ? $annotations['title'] : '');
        $schema = is_array($raw['inputSchema'] ?? null) ? $raw['inputSchema'] : [];

        return new RemoteTool(
            $name,
            $title,
            is_string($raw['description'] ?? null) ? $raw['description'] : '',
            $schema,
            ($annotations['readOnlyHint'] ?? false) === true,
            ($annotations['destructiveHint'] ?? false) === true
        );
    }

    /**
     * Turns content blocks into text a model can read.
     *
     * @param array<int, mixed> $content
     */
    private static function contentText(array $content): string
    {
        $parts = [];
        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            $parts[] = match ($block['type'] ?? '') {
                'text' => is_string($block['text'] ?? null) ? $block['text'] : '',
                'image' => '[image: ' . (is_string($block['mimeType'] ?? null) ? $block['mimeType'] : 'image') . ']',
                'audio' => '[audio]',
                'resource_link' => '[link: ' . (is_string($block['uri'] ?? null) ? $block['uri'] : '') . ']',
                'resource' => is_array($block['resource'] ?? null) && is_string($block['resource']['text'] ?? null)
                    ? $block['resource']['text']
                    : '[resource]',
                default => '',
            };
        }

        return trim(implode("\n\n", array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * Arguments the tool's schema asks to have repeated as headers (x-mcp-header).
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $arguments
     *
     * @return array<string, string>
     */
    private static function parameterHeaders(array $schema, array $arguments): array
    {
        $headers = [];
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        foreach ($properties as $property => $definition) {
            $header = is_array($definition) ? ($definition['x-mcp-header'] ?? null) : null;
            if (! is_string($header) || preg_match('/^[A-Za-z0-9-]{1,64}$/', $header) !== 1) {
                continue;
            }

            $value = $arguments[$property] ?? null;
            if (is_scalar($value)) {
                $headers['Mcp-Param-' . $header] = self::headerValue(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
        }

        return $headers;
    }

    private static function headerValue(string $value): string
    {
        return preg_match('/^[\x20-\x7E]*$/', $value) === 1 && trim($value) === $value
            ? $value
            : '=?base64?' . base64_encode($value) . '?=';
    }

    /**
     * @param array<string, mixed> $error
     */
    private static function errorText(array $error): string
    {
        $message = is_string($error['message'] ?? null) && $error['message'] !== '' ? $error['message'] : 'Unknown error';

        return 'The service reported a problem: ' . $message;
    }

    private static function httpProblem(HttpResponse $response): string
    {
        return match (true) {
            $response->status === 404 => 'Nothing was found at that address. Check the server URL.',
            $response->status >= 500 => sprintf('The service had a problem (HTTP %d). Try again later.', $response->status),
            $response->ok() => 'The address answered, but not like an MCP server. Check the server URL.',
            default => sprintf('The service refused the request (HTTP %d).', $response->status),
        };
    }
}
