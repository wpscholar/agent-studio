<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Connections;

use AgentStudio\Application\Mcp\McpClient;
use AgentStudio\Application\OAuth\OAuthDiscovery;
use AgentStudio\Contracts\Ability\AbilityResult;
use AgentStudio\Contracts\Connection\ConnectionType;
use AgentStudio\Domain\Connection\Connection;
use AgentStudio\Domain\Connection\RemoteTool;
use AgentStudio\Domain\Setup\SetupField;

/**
 * A remote MCP server, reached over HTTP. Its tools become tools agents can use.
 */
final class McpConnectionType implements ConnectionType
{
    public const KEY = 'mcp';

    public function __construct(private readonly McpClient $client)
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('MCP server', 'agent-studio');
    }

    public function description(): string
    {
        return __('Many services (GitHub, Linear, Notion, Stripe and others) offer an MCP server: a web address that lets AI agents use the service. Paste the address from the service’s documentation.', 'agent-studio');
    }

    public function fields(): array
    {
        return [
            new SetupField(
                'url',
                __('Server address', 'agent-studio'),
                SetupField::URL,
                __('The MCP server address from the service’s documentation. It usually starts with https:// and often ends in /mcp.', 'agent-studio'),
                true,
                'https://example.com/mcp'
            ),
        ];
    }

    public function validate(array $settings): ?string
    {
        $url = $settings['url'] ?? '';
        if ($url === '') {
            return __('Paste the server address.', 'agent-studio');
        }
        if (! OAuthDiscovery::isHttpUrl($url)) {
            return __('The server address must start with https:// (http:// only works for servers on this computer).', 'agent-studio');
        }

        return null;
    }

    public function resource(Connection $connection): string
    {
        return OAuthDiscovery::canonicalResource($connection->setting('url'));
    }

    public function tools(Connection $connection, array $headers): array
    {
        return $this->client->listTools($connection->setting('url'), $headers);
    }

    public function call(Connection $connection, array $headers, RemoteTool $tool, array $input): AbilityResult
    {
        $result = $this->client->callTool($connection->setting('url'), $headers, $tool->name, $input, $tool->inputSchema);

        $data = $result['structured'] !== null
            ? ['text' => $result['text'], 'data' => $result['structured']]
            : $result['text'];

        if ($result['isError']) {
            return AbilityResult::failure($result['text'] !== '' ? $result['text'] : __('The tool reported a problem.', 'agent-studio'));
        }

        return AbilityResult::success($data);
    }
}
