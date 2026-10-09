<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Connection;

/**
 * What Agent Studio learned about a service's sign-in: where to send people,
 * where to get tokens, and who we are to it. The tokens themselves live in the
 * secret store.
 */
final class OAuthConfig
{
    public const REGISTERED_BY_URL = 'metadata_document';
    public const REGISTERED_DYNAMICALLY = 'dynamic';
    public const REGISTERED_MANUALLY = 'manual';

    public function __construct(
        public readonly string $issuer,
        public readonly string $authorizationEndpoint,
        public readonly string $tokenEndpoint,
        public readonly string $clientId,
        public readonly string $registration,
        public readonly string $scope = '',
        public readonly string $resource = '',
        public readonly int $expiresAt = 0,
        public readonly bool $issuerInResponse = false,
    ) {
    }

    public function withExpiry(int $expiresAt): self
    {
        return new self(
            $this->issuer,
            $this->authorizationEndpoint,
            $this->tokenEndpoint,
            $this->clientId,
            $this->registration,
            $this->scope,
            $this->resource,
            $expiresAt,
            $this->issuerInResponse
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->authorizationEndpoint,
            'token_endpoint' => $this->tokenEndpoint,
            'client_id' => $this->clientId,
            'registration' => $this->registration,
            'scope' => $this->scope,
            'resource' => $this->resource,
            'expires_at' => $this->expiresAt,
            'iss_in_response' => $this->issuerInResponse,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $string = static fn (string $key): string => is_string($data[$key] ?? null) ? $data[$key] : '';
        if ($string('token_endpoint') === '' || $string('client_id') === '') {
            return null;
        }

        return new self(
            $string('issuer'),
            $string('authorization_endpoint'),
            $string('token_endpoint'),
            $string('client_id'),
            $string('registration'),
            $string('scope'),
            $string('resource'),
            is_int($data['expires_at'] ?? null) ? $data['expires_at'] : 0,
            ($data['iss_in_response'] ?? false) === true
        );
    }
}
