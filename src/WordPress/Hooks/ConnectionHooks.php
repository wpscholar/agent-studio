<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Hooks;

use Throwable;
use AgentStudio\Application\Connections\ConnectionService;
use AgentStudio\Contracts\ConnectionRepository;
use AgentStudio\Domain\Connection\ConnectionStatus;
use AgentStudio\Infrastructure\Secrets\SecretStoreFactory;

/**
 * Keeps connections fresh: once a day each one is asked for its tools again,
 * so new tools appear and expired sign-ins show up on the Connections screen.
 */
final class ConnectionHooks
{
    public const HOOK = 'agent_studio_check_connections';

    public function __construct(
        private readonly ConnectionRepository $connections,
        private readonly ConnectionService $service,
    ) {
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'checkAll']);
        add_action('admin_init', [SecretStoreFactory::class, 'migrateToCore']);
    }

    public static function schedule(): void
    {
        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public function checkAll(): void
    {
        foreach ($this->connections->all() as $connection) {
            if ($connection->status === ConnectionStatus::NeedsSignIn) {
                continue;
            }

            try {
                $this->service->check($connection);
            } catch (Throwable) {
                // One broken connection must not stop the others.
            }
        }
    }
}
