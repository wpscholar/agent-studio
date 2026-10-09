<?php

declare(strict_types=1);

namespace AgentStudio\Application\Triggers;

use InvalidArgumentException;
use AgentStudio\Contracts\Trigger\TriggerDefinition;

/**
 * Event kinds plugins added on this site.
 */
final class TriggerDefinitions
{
    public const KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]*\/[a-z0-9][a-z0-9_.\/-]{0,100}$/';

    /** @var array<string, TriggerDefinition> */
    private array $definitions = [];

    public function register(TriggerDefinition $definition): void
    {
        if (preg_match(self::KEY_PATTERN, $definition->key()) !== 1) {
            throw new InvalidArgumentException('A trigger key needs a namespace, like "my-plugin/order-placed": lowercase letters, numbers, hyphens and underscores.');
        }

        $this->definitions[$definition->key()] = $definition;
    }

    public function get(string $key): ?TriggerDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /**
     * @return list<TriggerDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
