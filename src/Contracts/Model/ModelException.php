<?php

declare(strict_types=1);

namespace WPAgents\Contracts\Model;

use RuntimeException;

/**
 * The model could not produce a reply: not configured, rate limited, provider error.
 */
final class ModelException extends RuntimeException
{
}
