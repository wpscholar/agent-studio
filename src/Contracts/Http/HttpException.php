<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Http;

use RuntimeException;

/**
 * The request never got an answer: the address could not be reached, timed out, or was refused for safety.
 */
final class HttpException extends RuntimeException
{
}
