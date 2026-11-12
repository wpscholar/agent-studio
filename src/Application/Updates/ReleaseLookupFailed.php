<?php

declare(strict_types=1);

namespace AgentStudio\Application\Updates;

use RuntimeException;

/**
 * GitHub could not be asked about releases right now: unreachable, rate limited, or an answer we could not read.
 */
final class ReleaseLookupFailed extends RuntimeException
{
}
