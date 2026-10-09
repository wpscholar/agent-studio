<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Connection;

use RuntimeException;

/**
 * The service wants credentials, or better ones. Carries its WWW-Authenticate
 * header so sign-in can be discovered from it.
 */
class AuthRequired extends RuntimeException
{
    public const DEFAULT_MESSAGE = 'The service needs you to sign in or give it a key.';

    public function __construct(public readonly string $wwwAuthenticate = '', string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::DEFAULT_MESSAGE);
    }
}
