<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Application\Mcp\McpClient;
use AgentStudio\Contracts\Ability\AbilityResult;
use AgentStudio\Contracts\Connection\ConnectionType;
use AgentStudio\Domain\Connection\Connection;
use AgentStudio\Domain\Connection\RemoteTool;

/**
 * The MCP connection type without WordPress translations, for unit tests.
 */
final class McpTestConnectionType implements ConnectionType
{
    public function __construct(private readonly McpClient $client)
    {
    }

    public function key(): string
    {
        return 'mcp';
    }

    public function label(): string
    {
        return 'MCP server';
    }

    public function description(): string
    {
        return '';
    }

    public function fields(): array
    {
        return [];
    }

    public function validate(array $settings): ?string
    {
        return null;
    }

    public function resource(Connection $connection): string
    {
        return $connection->setting('url');
    }

    public function tools(Connection $connection, array $headers): array
    {
        return $this->client->listTools($connection->setting('url'), $headers);
    }

    public function call(Connection $connection, array $headers, RemoteTool $tool, array $input): AbilityResult
    {
        $result = $this->client->callTool($connection->setting('url'), $headers, $tool->name, $input, $tool->inputSchema);

        return $result['isError'] ? AbilityResult::failure($result['text']) : AbilityResult::success($result['text']);
    }
}
