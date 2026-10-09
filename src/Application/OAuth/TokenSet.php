<?php

declare(strict_types=1);

namespace AgentStudio\Application\OAuth;

final class TokenSet
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
        public readonly string $scope,
    ) {
    }

    /**
     * @param array<string, mixed> $data A token endpoint response.
     */
    public static function fromResponse(array $data): self
    {
        $access = is_string($data['access_token'] ?? null) ? $data['access_token'] : '';
        if ($access === '') {
            throw new OAuthException('The service did not send an access token.');
        }

        $type = is_string($data['token_type'] ?? null) ? strtolower($data['token_type']) : 'bearer';
        if ($type !== 'bearer') {
            throw new OAuthException('The service sent a kind of token Agent Studio cannot use.');
        }

        return new self(
            $access,
            is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : '',
            is_numeric($data['expires_in'] ?? null) ? max(0, (int) $data['expires_in']) : 0,
            is_string($data['scope'] ?? null) ? $data['scope'] : ''
        );
    }
}
