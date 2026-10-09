<?php

declare(strict_types=1);

namespace AgentStudio\Application\OAuth;

/**
 * Proof Key for Code Exchange (RFC 7636), S256 only.
 */
final class Pkce
{
    public static function verifier(): string
    {
        return self::base64url(random_bytes(32));
    }

    public static function challenge(string $verifier): string
    {
        return self::base64url(hash('sha256', $verifier, true));
    }

    public static function state(): string
    {
        return self::base64url(random_bytes(24));
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
