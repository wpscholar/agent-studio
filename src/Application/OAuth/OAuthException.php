<?php

declare(strict_types=1);

namespace AgentStudio\Application\OAuth;

use RuntimeException;

/**
 * Signing in could not go ahead. The message is written for the person setting it up.
 */
class OAuthException extends RuntimeException
{
}
