<?php

declare(strict_types=1);

namespace AgentStudio\Application\OAuth;

/**
 * The service supports sign-in but cannot register Agent Studio automatically,
 * so the person has to create an app there and paste its client ID.
 */
final class ManualClientNeeded extends OAuthException
{
    /**
     * @param string $documentation The service's own setup instructions, when its metadata names them.
     */
    public function __construct(
        public readonly string $issuer,
        public readonly string $documentation = '',
    ) {
        parent::__construct(
            'This service does not let apps register themselves. Create an app (sometimes called an OAuth client) in the service’s developer settings, then paste its client ID here.'
        );
    }
}
