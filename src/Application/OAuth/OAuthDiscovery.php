<?php

declare(strict_types=1);

namespace AgentStudio\Application\OAuth;

use AgentStudio\Contracts\Http\HttpClient;
use AgentStudio\Contracts\Http\HttpException;
use AgentStudio\Contracts\Http\HttpRequest;

/**
 * Finds out how to sign in to a protected service, following the MCP
 * authorization spec: protected resource metadata (RFC 9728), then the
 * authorization server's metadata (RFC 8414 or OpenID discovery).
 */
final class OAuthDiscovery
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * The parameters of a WWW-Authenticate challenge, like resource_metadata and scope.
     *
     * @return array<string, string>
     */
    public static function challenge(string $header): array
    {
        $params = [];
        preg_match_all('/([A-Za-z_][A-Za-z0-9_-]*)\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^\s,]+))/', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $params[strtolower($match[1])] = isset($match[3]) && $match[3] !== '' ? $match[3] : stripslashes($match[2] ?? '');
        }

        return $params;
    }

    /**
     * The resource identifier to ask tokens for: the server URL without fragment or trailing slash.
     */
    public static function canonicalResource(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $resource = strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = rtrim($parts['path'] ?? '', '/');

        return $resource . $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * What the protected service says about itself: which authorization servers
     * issue its tokens, and which scopes it knows.
     *
     * @return array{resource: string, authorization_servers: list<string>, scopes_supported: list<string>}
     */
    public function protectedResource(string $serverUrl, string $wwwAuthenticate): array
    {
        $challenge = self::challenge($wwwAuthenticate);
        $candidates = [];
        if (isset($challenge['resource_metadata']) && self::isHttpUrl($challenge['resource_metadata'])) {
            $candidates[] = $challenge['resource_metadata'];
        }

        [$origin, $path] = self::split($serverUrl);
        if ($path !== '') {
            $candidates[] = $origin . '/.well-known/oauth-protected-resource' . $path;
        }
        $candidates[] = $origin . '/.well-known/oauth-protected-resource';

        foreach (array_unique($candidates) as $url) {
            $data = $this->getJson($url);
            $servers = is_array($data['authorization_servers'] ?? null)
                ? array_values(array_filter($data['authorization_servers'], [self::class, 'isHttpUrl']))
                : [];

            if ($servers !== []) {
                return [
                    'resource' => is_string($data['resource'] ?? null) ? $data['resource'] : self::canonicalResource($serverUrl),
                    'authorization_servers' => $servers,
                    'scopes_supported' => self::strings($data['scopes_supported'] ?? []),
                ];
            }
        }

        // Servers written for the 2025-03-26 spec publish no resource metadata; their own origin signs people in.
        return [
            'resource' => self::canonicalResource($serverUrl),
            'authorization_servers' => [$origin],
            'scopes_supported' => [],
        ];
    }

    /**
     * The authorization server's endpoints. The issuer in the document must match exactly.
     *
     * @return array<string, mixed>
     */
    public function authorizationServer(string $issuer): array
    {
        [$origin, $path] = self::split($issuer);
        $candidates = $path === ''
            ? [$origin . '/.well-known/oauth-authorization-server', $origin . '/.well-known/openid-configuration']
            : [
                $origin . '/.well-known/oauth-authorization-server' . $path,
                $origin . '/.well-known/openid-configuration' . $path,
                $origin . $path . '/.well-known/openid-configuration',
            ];

        foreach ($candidates as $url) {
            $data = $this->getJson($url);
            if ($data === null) {
                continue;
            }

            if (($data['issuer'] ?? null) !== $issuer) {
                throw new OAuthException('The sign-in server’s details do not match its address, so Agent Studio will not trust it.');
            }

            foreach (['authorization_endpoint', 'token_endpoint'] as $key) {
                if (! is_string($data[$key] ?? null) || ! self::isHttpUrl($data[$key])) {
                    throw new OAuthException('The sign-in server’s details are incomplete.');
                }
            }

            if (! in_array('S256', self::strings($data['code_challenge_methods_supported'] ?? []), true)) {
                throw new OAuthException('The sign-in server does not support the secure sign-in method Agent Studio requires (PKCE).');
            }

            return $data;
        }

        throw new OAuthException('The sign-in server could not be found.');
    }

    /**
     * Scopes to ask for: what the service's challenge names, else what it supports, else none.
     *
     * @param array<string, mixed> $authServer
     * @param list<string>         $resourceScopes
     */
    public static function scopeFor(string $wwwAuthenticate, array $resourceScopes, array $authServer): string
    {
        $challenge = self::challenge($wwwAuthenticate);
        $scopes = isset($challenge['scope']) ? preg_split('/\s+/', trim($challenge['scope'])) : $resourceScopes;
        $scopes = array_values(array_filter($scopes ?: [], static fn ($scope): bool => is_string($scope) && $scope !== ''));

        if (in_array('offline_access', self::strings($authServer['scopes_supported'] ?? []), true) && ! in_array('offline_access', $scopes, true)) {
            $scopes[] = 'offline_access';
        }

        return implode(' ', $scopes);
    }

    public static function isHttpUrl(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host !== '' && ($scheme === 'https' || ($scheme === 'http' && self::isLocal($host)));
    }

    private static function isLocal(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '[::1]', '::1'], true) || str_ends_with(strtolower($host), '.localhost');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getJson(string $url): ?array
    {
        try {
            $response = $this->http->send(new HttpRequest('GET', $url, ['Accept' => 'application/json'], '', 15));
        } catch (HttpException) {
            return null;
        }

        return $response->ok() ? $response->json() : null;
    }

    /**
     * @return array{0: string, 1: string} Origin and path without trailing slash.
     */
    private static function split(string $url): array
    {
        $parts = parse_url($url);
        $origin = strtolower(($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')) . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return [$origin, rtrim($parts['path'] ?? '', '/')];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
