<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Connection;

use InvalidArgumentException;

/**
 * Another service agents can use, such as a remote MCP server. Its tools show
 * up next to the site's own abilities in each agent's list.
 */
final class Connection
{
    /**
     * @param array<string, string> $settings   Non-secret setup values, such as the server URL.
     * @param list<RemoteTool>      $tools      As last listed by the service.
     * @param string                $keyHeader  Header that carries the API key. "Authorization" sends "Bearer <key>".
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $slug,
        public readonly array $settings = [],
        public readonly AuthMode $authMode = AuthMode::None,
        public readonly string $keyHeader = 'Authorization',
        public readonly ConnectionStatus $status = ConnectionStatus::New,
        public readonly string $message = '',
        public readonly array $tools = [],
        public readonly int $checkedAt = 0,
        public readonly ?OAuthConfig $oauth = null,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $slug) !== 1) {
            throw new InvalidArgumentException('A connection ID uses lowercase letters, numbers and hyphens, up to 40 characters.');
        }
    }

    public function setting(string $key): string
    {
        return $this->settings[$key] ?? '';
    }

    /**
     * The namespace its tools use in agents' ability lists, like "mcp-github".
     */
    public function abilityNamespace(): string
    {
        return 'mcp-' . $this->slug;
    }

    public function tool(string $name): ?RemoteTool
    {
        foreach ($this->tools as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * A copy with some properties changed.
     *
     * @param array<string, mixed> $changes Property names to new values.
     */
    public function with(array $changes): self
    {
        $values = get_object_vars($this);
        foreach ($changes as $key => $value) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException(sprintf('Connection has no property "%s".', $key));
            }
            $values[$key] = $value;
        }

        /** @var array{id: int, type: string, name: string, slug: string, settings: array<string, string>, authMode: AuthMode, keyHeader: string, status: ConnectionStatus, message: string, tools: list<RemoteTool>, checkedAt: int, oauth: ?OAuthConfig} $values */
        return new self(...$values);
    }
}
