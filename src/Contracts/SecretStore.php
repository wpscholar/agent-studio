<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

/**
 * Keeps credentials (API keys, tokens) out of ordinary settings.
 *
 * Names are short, lowercase and unique within the plugin, like "connection-12-token".
 * Values are never shown again once saved.
 */
interface SecretStore
{
    public function get(string $name): ?string;

    public function set(string $name, string $value): void;

    public function delete(string $name): void;
}
