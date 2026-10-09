<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Connection;

use AgentStudio\Contracts\Ability\AbilityResult;
use AgentStudio\Domain\Connection\Connection;
use AgentStudio\Domain\Connection\RemoteTool;
use AgentStudio\Domain\Setup\SetupField;

/**
 * A kind of service agents can connect to. Agent Studio ships "MCP server";
 * plugins can add their own on the agent_studio_register_connection_types action.
 *
 * Agent Studio handles the rest for every type: storing settings and secrets,
 * API keys or OAuth sign-in, the Connections screen, and offering the tools to agents.
 */
interface ConnectionType
{
    /** Short unique key, like "mcp". */
    public function key(): string;

    /** Name people see, like "MCP server". */
    public function label(): string;

    /** One or two plain sentences: what this connects to and when to pick it. */
    public function description(): string;

    /**
     * Setup fields besides the name and credentials, like the server address.
     *
     * @return list<SetupField>
     */
    public function fields(): array;

    /**
     * A problem with the settings, written for people, or null when they look right.
     *
     * @param array<string, string> $settings
     */
    public function validate(array $settings): ?string;

    /**
     * The address that sign-in tokens are issued for and where sign-in discovery starts.
     */
    public function resource(Connection $connection): string;

    /**
     * Asks the service which tools it offers.
     *
     * @param array<string, string> $headers Credentials to send.
     *
     * @return list<RemoteTool>
     *
     * @throws AuthRequired      When the service wants (better) credentials.
     * @throws ConnectionProblem For anything else.
     */
    public function tools(Connection $connection, array $headers): array;

    /**
     * Runs one tool. A tool that ran and reported a problem is a failed AbilityResult.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>  $input
     *
     * @throws AuthRequired
     * @throws ConnectionProblem
     */
    public function call(Connection $connection, array $headers, RemoteTool $tool, array $input): AbilityResult;
}
