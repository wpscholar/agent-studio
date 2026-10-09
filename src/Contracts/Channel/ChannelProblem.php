<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

use RuntimeException;

/**
 * The channel's service refused or could not be reached. The message is written for people.
 */
class ChannelProblem extends RuntimeException
{
}
