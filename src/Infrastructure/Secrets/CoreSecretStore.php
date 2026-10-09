<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure\Secrets;

use RuntimeException;
use AgentStudio\Contracts\SecretStore;

/**
 * The WordPress Secrets API (proposed for WordPress 7.2): encrypted storage that
 * hosts can move to their own key management. Used whenever it is available.
 */
final class CoreSecretStore implements SecretStore
{
    private const PREFIX = 'agent-studio/';

    public static function available(): bool
    {
        return function_exists('wp_set_secret') && function_exists('wp_get_secret') && function_exists('wp_delete_secret');
    }

    public function get(string $name): ?string
    {
        $secret = wp_get_secret(self::PREFIX . $name);
        if (! is_object($secret) || ! method_exists($secret, 'reveal')) {
            return null;
        }

        $value = $secret->reveal();

        return is_string($value) ? $value : null;
    }

    public function set(string $name, string $value): void
    {
        $result = wp_set_secret(self::PREFIX . $name, $value);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; shown escaped by the caller.
        }
    }

    public function delete(string $name): void
    {
        wp_delete_secret(self::PREFIX . $name);
    }
}
