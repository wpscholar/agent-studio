<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Contracts\PendingSignInStore;

final class TransientPendingSignInStore implements PendingSignInStore
{
    private const PREFIX = 'agent_studio_signin_';

    public function put(string $state, array $data, int $ttlSeconds): void
    {
        set_transient(self::key($state), $data, $ttlSeconds);
    }

    public function take(string $state): ?array
    {
        $key = self::key($state);
        $data = get_transient($key);
        delete_transient($key);

        return is_array($data) ? $data : null;
    }

    private static function key(string $state): string
    {
        return self::PREFIX . md5($state);
    }
}
