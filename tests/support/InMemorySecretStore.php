<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\SecretStore;

final class InMemorySecretStore implements SecretStore
{
    /** @var array<string, string> */
    public array $values = [];

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function set(string $name, string $value): void
    {
        $this->values[$name] = $value;
    }

    public function delete(string $name): void
    {
        unset($this->values[$name]);
    }
}
