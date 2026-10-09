<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure\Secrets;

use RuntimeException;
use SodiumException;
use AgentStudio\Contracts\SecretStore;

/**
 * Used until the WordPress Secrets API is available.
 *
 * Each secret is encrypted with libsodium and kept in its own option that
 * WordPress does not load on every page. The key comes from
 * AGENT_STUDIO_SECRETS_KEY if defined, otherwise from the security keys in
 * wp-config.php, so a copy of the database alone does not reveal the secrets.
 * Changing those keys makes saved secrets unreadable: people are asked to
 * reconnect, nothing breaks.
 */
final class EncryptedOptionSecretStore implements SecretStore
{
    public const OPTION_PREFIX = 'agent_studio_secret_';
    public const INDEX_OPTION = 'agent_studio_secret_index';

    private const VERSION = 'v1:';

    public function get(string $name): ?string
    {
        $stored = get_option(self::option($name));
        if (! is_string($stored) || ! str_starts_with($stored, self::VERSION)) {
            return null;
        }

        $raw = base64_decode(substr($stored, strlen(self::VERSION)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        try {
            $plain = sodium_crypto_secretbox_open(
                substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                self::key()
            );
        } catch (SodiumException) {
            return null;
        }

        return $plain === false ? null : $plain;
    }

    public function set(string $name, string $value): void
    {
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($value, $nonce, self::key());
        } catch (SodiumException $e) {
            throw new RuntimeException(esc_html__('The secret could not be encrypted.', 'agent-studio'), 0, $e); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- the previous exception is not output.
        }

        update_option(self::option($name), self::VERSION . base64_encode($nonce . $cipher), false);

        $index = self::names();
        if (! in_array($name, $index, true)) {
            $index[] = $name;
            update_option(self::INDEX_OPTION, $index, false);
        }
    }

    public function delete(string $name): void
    {
        delete_option(self::option($name));
        update_option(self::INDEX_OPTION, array_values(array_diff(self::names(), [$name])), false);
    }

    /**
     * Every secret name stored here, so they can be moved or removed.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $index = get_option(self::INDEX_OPTION, []);

        return is_array($index) ? array_values(array_filter($index, 'is_string')) : [];
    }

    private static function option(string $name): string
    {
        return self::OPTION_PREFIX . md5($name);
    }

    private static function key(): string
    {
        $material = defined('AGENT_STUDIO_SECRETS_KEY') && is_string(AGENT_STUDIO_SECRETS_KEY) && AGENT_STUDIO_SECRETS_KEY !== ''
            ? AGENT_STUDIO_SECRETS_KEY
            : wp_salt('secure_auth');

        return hash_hkdf('sha256', $material, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'agent-studio-secrets');
    }
}
