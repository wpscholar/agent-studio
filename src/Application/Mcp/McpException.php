<?php

declare(strict_types=1);

namespace AgentStudio\Application\Mcp;

use AgentStudio\Contracts\Connection\ConnectionProblem;

/**
 * The server answered, but not with what was asked for. The message is written for people.
 */
class McpException extends ConnectionProblem
{
}
