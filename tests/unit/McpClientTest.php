<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Application\Mcp\McpClient;
use AgentStudio\Application\Mcp\McpException;
use AgentStudio\Application\Mcp\SseParser;
use AgentStudio\Contracts\Connection\AuthRequired;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Contracts\Http\HttpResponse;
use AgentStudio\Domain\Ability\Risk;
use AgentStudio\Tests\Support\FakeHttpClient;
use AgentStudio\Tests\Support\FakeMcpServer;

final class McpClientTest extends TestCase
{
    private const URL = 'https://mcp.example.com/mcp';

    public function testListsToolsWithTheirRisk(): void
    {
        $server = new FakeMcpServer();
        $client = new McpClient(new FakeHttpClient([$server, 'handle']));

        $tools = $client->listTools(self::URL);

        $this->assertSame(['search_issues', 'create_issue', 'delete_repo'], array_map(static fn ($t) => $t->name, $tools));
        $this->assertSame('Search issues', $tools[0]->title);
        $this->assertSame(Risk::Read, $tools[0]->risk());
        $this->assertSame(Risk::External, $tools[1]->risk(), 'A tool that does not say what it does acts outside the site.');
        $this->assertSame(Risk::Destructive, $tools[2]->risk());
    }

    public function testSendsTheCurrentProtocolOnEveryRequest(): void
    {
        $http = new FakeHttpClient([new FakeMcpServer(), 'handle']);

        (new McpClient($http, 'Test', '1.0'))->callTool(self::URL, ['Authorization' => 'Bearer t'], 'create_issue', ['title' => 'Hi']);

        $request = $http->requests[0];
        $this->assertSame('2026-07-28', $request->headers['MCP-Protocol-Version']);
        $this->assertSame('tools/call', $request->headers['Mcp-Method']);
        $this->assertSame('create_issue', $request->headers['Mcp-Name']);
        $this->assertSame('application/json, text/event-stream', $request->headers['Accept']);
        $this->assertSame('Bearer t', $request->headers['Authorization']);
        $meta = FakeHttpClient::body($request)['params']['_meta'];
        $this->assertSame('2026-07-28', $meta['io.modelcontextprotocol/protocolVersion']);
        $this->assertSame(['name' => 'Test', 'version' => '1.0'], $meta['io.modelcontextprotocol/clientInfo']);
    }

    public function testFollowsPagesOfTools(): void
    {
        $http = new FakeHttpClient(static function (HttpRequest $request): HttpResponse {
            $body = FakeHttpClient::body($request);
            $cursor = $body['params']['cursor'] ?? null;

            return FakeHttpClient::json(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => $cursor === null
                ? ['tools' => [['name' => 'one']], 'nextCursor' => 'p2']
                : ['tools' => [['name' => 'two']]]]);
        });

        $tools = (new McpClient($http))->listTools(self::URL);

        $this->assertSame(['one', 'two'], array_map(static fn ($t) => $t->name, $tools));
    }

    public function testReadsAnswersSentAsAnEventStream(): void
    {
        $server = new FakeMcpServer();
        $server->stream = true;

        $result = (new McpClient(new FakeHttpClient([$server, 'handle'])))->callTool(self::URL, [], 'create_issue', ['title' => 'Hi']);

        $this->assertFalse($result['isError']);
        $this->assertSame('Done: {"title":"Hi"}', $result['text']);
    }

    public function testAToolThatFailsComesBackAsAnError(): void
    {
        $result = (new McpClient(new FakeHttpClient([new FakeMcpServer(), 'handle'])))->callTool(self::URL, [], 'delete_repo', []);

        $this->assertTrue($result['isError']);
        $this->assertSame('Not allowed.', $result['text']);
    }

    public function testAskingForCredentialsCarriesTheChallenge(): void
    {
        $server = new FakeMcpServer(token: 'secret');

        try {
            (new McpClient(new FakeHttpClient([$server, 'handle'])))->listTools(self::URL);
            $this->fail('Expected AuthRequired.');
        } catch (AuthRequired $e) {
            $this->assertStringContainsString('resource_metadata=', $e->wwwAuthenticate);
        }
    }

    public function testFallsBackToTheOlderHandshake(): void
    {
        $initialized = false;
        $http = new FakeHttpClient(static function (HttpRequest $request) use (&$initialized): ?HttpResponse {
            if ($request->method === 'DELETE') {
                return new HttpResponse(200, [], '');
            }

            $body = FakeHttpClient::body($request);
            $method = $body['method'] ?? '';
            if ($method === 'initialize') {
                $initialized = true;

                return FakeHttpClient::json(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => ['protocolVersion' => '2025-06-18', 'capabilities' => []]], 200, ['mcp-session-id' => 'abc']);
            }
            if ($method === 'notifications/initialized') {
                return new HttpResponse(202, [], '');
            }
            if (($request->headers['Mcp-Session-Id'] ?? '') !== 'abc') {
                return new HttpResponse(400, ['content-type' => 'text/plain'], 'Bad Request: No valid session ID provided');
            }

            return FakeHttpClient::json(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => ['tools' => [['name' => 'legacy_tool']]]]);
        });

        $client = new McpClient($http);
        $tools = $client->listTools(self::URL);

        $this->assertTrue($initialized);
        $this->assertSame('legacy_tool', $tools[0]->name);
        $this->assertTrue($client->usedLegacy(self::URL));
        $this->assertSame('2025-06-18', $http->requests[3]->headers['MCP-Protocol-Version']);
        $this->assertArrayNotHasKey('_meta', (array) FakeHttpClient::body($http->requests[3])['params']);
    }

    public function testAnOlderServerThatAnswersWithAnErrorWithoutIdAlsoFallsBack(): void
    {
        $http = new FakeHttpClient(static function (HttpRequest $request): HttpResponse {
            $body = FakeHttpClient::body($request);
            if (isset($body['params']['_meta'])) {
                return FakeHttpClient::json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32000, 'message' => 'Server not initialized']], 400);
            }
            if (($body['method'] ?? '') === 'initialize') {
                return FakeHttpClient::json(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => ['protocolVersion' => '2025-03-26']]);
            }

            return FakeHttpClient::json(['jsonrpc' => '2.0', 'id' => $body['id'] ?? null, 'result' => ['tools' => []]]);
        });

        $this->assertSame([], (new McpClient($http))->listTools(self::URL));
    }

    public function testExplainsAnAddressThatIsNotAnMcpServer(): void
    {
        $http = new FakeHttpClient(static fn (): HttpResponse => new HttpResponse(200, ['content-type' => 'text/html'], '<html>'));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('not like an MCP server');

        (new McpClient($http))->listTools(self::URL);
    }

    public function testExplainsAnUnreachableServer(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('could not be reached');

        (new McpClient(new FakeHttpClient(static fn () => null)))->listTools(self::URL);
    }

    public function testRepeatsArgumentsTheToolWantsAsHeaders(): void
    {
        $http = new FakeHttpClient([new FakeMcpServer(), 'handle']);
        $schema = ['type' => 'object', 'properties' => ['region' => ['type' => 'string', 'x-mcp-header' => 'Region'], 'note' => ['type' => 'string', 'x-mcp-header' => 'Note']]];

        (new McpClient($http))->callTool(self::URL, [], 'create_issue', ['region' => 'eu', 'note' => 'café'], $schema);

        $this->assertSame('eu', $http->requests[0]->headers['Mcp-Param-Region']);
        $this->assertSame('=?base64?' . base64_encode('café') . '?=', $http->requests[0]->headers['Mcp-Param-Note']);
    }

    public function testParsesEventStreams(): void
    {
        $messages = SseParser::messages(": comment\r\nevent: message\r\ndata: {\"a\":\r\ndata: 1}\r\n\r\ndata: not json\n\ndata: {\"b\":2}\n\n");

        $this->assertSame([['a' => 1], ['b' => 2]], $messages);
    }
}
