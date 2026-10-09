<?php

declare(strict_types=1);

namespace AgentStudio\Application\OAuth;

use AgentStudio\Contracts\Http\HttpClient;
use AgentStudio\Contracts\Http\HttpException;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Contracts\Http\HttpResponse;
use AgentStudio\Domain\Connection\OAuthConfig;

/**
 * The OAuth 2.1 calls Agent Studio makes as a client: register itself, build
 * the sign-in link, and trade codes and refresh tokens for access tokens.
 */
final class OAuthClient
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Dynamic client registration (RFC 7591).
     *
     * @return array{client_id: string, client_secret: string}
     */
    public function register(string $registrationEndpoint, string $redirectUri, string $clientName, string $clientUri): array
    {
        $response = $this->send(
            HttpRequest::json(
                $registrationEndpoint,
                [
                    'client_name' => $clientName,
                    'client_uri' => $clientUri,
                    'redirect_uris' => [$redirectUri],
                    'grant_types' => ['authorization_code', 'refresh_token'],
                    'response_types' => ['code'],
                    'token_endpoint_auth_method' => 'none',
                    'application_type' => 'web',
                ],
                ['Accept' => 'application/json'],
                20
            )
        );

        $data = $response->json() ?? [];
        $clientId = is_string($data['client_id'] ?? null) ? $data['client_id'] : '';
        if (! $response->ok() || $clientId === '') {
            throw new OAuthException('The service would not register Agent Studio as an app. ' . self::problem($data, $response));
        }

        return [
            'client_id' => $clientId,
            'client_secret' => is_string($data['client_secret'] ?? null) ? $data['client_secret'] : '',
        ];
    }

    public function authorizationUrl(OAuthConfig $config, string $redirectUri, string $state, string $challenge): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $config->clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];
        if ($config->resource !== '') {
            $params['resource'] = $config->resource;
        }
        if ($config->scope !== '') {
            $params['scope'] = $config->scope;
        }

        $separator = str_contains($config->authorizationEndpoint, '?') ? '&' : '?';

        return $config->authorizationEndpoint . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(OAuthConfig $config, string $code, string $verifier, string $redirectUri, string $clientSecret): TokenSet
    {
        return $this->token($config, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $verifier,
        ], $clientSecret);
    }

    public function refresh(OAuthConfig $config, string $refreshToken, string $clientSecret): TokenSet
    {
        return $this->token($config, ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken], $clientSecret);
    }

    /**
     * @param array<string, string> $fields
     */
    private function token(OAuthConfig $config, array $fields, string $clientSecret): TokenSet
    {
        if ($config->resource !== '') {
            $fields['resource'] = $config->resource;
        }

        $headers = [];
        if ($clientSecret === '') {
            $fields['client_id'] = $config->clientId;
        } else {
            $headers['Authorization'] = 'Basic ' . base64_encode(rawurlencode($config->clientId) . ':' . rawurlencode($clientSecret));
        }

        $response = $this->send(HttpRequest::form($config->tokenEndpoint, $fields, $headers));
        $data = $response->json() ?? [];
        if (! $response->ok()) {
            throw new OAuthException('The service did not accept the sign-in. ' . self::problem($data, $response));
        }

        return TokenSet::fromResponse($data);
    }

    private function send(HttpRequest $request): HttpResponse
    {
        try {
            return $this->http->send($request);
        } catch (HttpException $e) {
            throw new OAuthException('The sign-in server could not be reached: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function problem(array $data, HttpResponse $response): string
    {
        $description = is_string($data['error_description'] ?? null) ? $data['error_description'] : '';
        $error = is_string($data['error'] ?? null) ? $data['error'] : '';

        return trim($description !== '' ? $description : ($error !== '' ? $error : sprintf('(HTTP %d)', $response->status)));
    }
}
