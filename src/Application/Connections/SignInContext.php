<?php

declare(strict_types=1);

namespace AgentStudio\Application\Connections;

/**
 * Who is signing in, and how this site introduces itself to the service.
 */
final class SignInContext
{
    /**
     * @param string $clientMetadataUrl HTTPS address of this site's client metadata document, or empty when the site is not on HTTPS.
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $redirectUri,
        public readonly string $clientMetadataUrl,
        public readonly string $clientName,
        public readonly string $clientUri,
    ) {
    }
}
