<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Connection;

use RuntimeException;

/**
 * The service could not be used, for a reason other than credentials. The message is written for people.
 */
class ConnectionProblem extends RuntimeException
{
}
