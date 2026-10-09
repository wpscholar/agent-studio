<?php

declare(strict_types=1);

namespace AgentStudio\Application\Connections;

use AgentStudio\Contracts\Ability\AbilityResult;
use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Contracts\ConnectionRepository;
use AgentStudio\Domain\Ability\AbilityInfo;
use AgentStudio\Domain\Ability\SchemaNormalizer;
use AgentStudio\Domain\Connection\Connection;
use AgentStudio\Domain\Connection\RemoteTool;

/**
 * Offers the tools of each connection to agents, named "mcp-<connection>/<tool>".
 *
 * Only agents see them: they are not registered with the WordPress Abilities API.
 * Lists come from what the service last reported, so building an agent's tool
 * list never waits on the network; only running a tool does.
 */
final class ConnectionAbilityGateway implements AbilityGateway
{
    public const PREFIX = 'mcp-';

    public function __construct(
        private readonly ConnectionRepository $connections,
        private readonly ConnectionService $service,
    ) {
    }

    public function available(): array
    {
        $infos = [];
        foreach ($this->connections->all() as $connection) {
            foreach ($connection->tools as $tool) {
                $infos[] = self::describe($connection, $tool);
            }
        }

        return $infos;
    }

    public function execute(string $abilityName, array $input): AbilityResult
    {
        [$namespace, $toolPart] = array_pad(explode('/', $abilityName, 2), 2, '');
        $connection = str_starts_with($namespace, self::PREFIX)
            ? $this->connections->findBySlug(substr($namespace, strlen(self::PREFIX)))
            : null;

        if ($connection === null) {
            return AbilityResult::failure(sprintf('The connection behind "%s" no longer exists.', $abilityName));
        }

        foreach ($connection->tools as $tool) {
            if (self::toolPart($tool->name) === $toolPart) {
                $normalized = SchemaNormalizer::forModel($tool->inputSchema);
                $arguments = $normalized['wrapped'] ? (array) ($input['input'] ?? []) : $input;

                return $this->service->call($connection, $tool->name, $arguments);
            }
        }

        return AbilityResult::failure(sprintf('%s no longer offers this tool.', $connection->name));
    }

    public function handles(string $abilityName): bool
    {
        return str_starts_with($abilityName, self::PREFIX);
    }

    public static function abilityName(Connection $connection, RemoteTool $tool): string
    {
        return $connection->abilityNamespace() . '/' . self::toolPart($tool->name);
    }

    /**
     * Tool names can contain characters ability names do not allow.
     */
    private static function toolPart(string $name): string
    {
        $part = preg_replace('/[^A-Za-z0-9_.-]/', '-', $name) ?? '';

        return preg_match('/^[A-Za-z0-9]/', $part) === 1 ? $part : 't' . $part;
    }

    private static function describe(Connection $connection, RemoteTool $tool): AbilityInfo
    {
        $normalized = SchemaNormalizer::forModel($tool->inputSchema);

        return new AbilityInfo(
            self::abilityName($connection, $tool),
            $tool->title !== '' ? $tool->title : $tool->name,
            // Said plainly so the model knows the result comes from outside the site.
            trim($tool->description . ' (Provided by the connected service "' . $connection->name . '".)'),
            $connection->name,
            $tool->risk(),
            $normalized['schema'],
            $normalized['wrapped']
        );
    }
}
