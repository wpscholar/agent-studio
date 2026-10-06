<?php

declare(strict_types=1);

namespace WPAgents\Domain\Run;

use LogicException;

final class InvalidRunTransition extends LogicException
{
    public static function between(RunStatus $from, string $action): self
    {
        return new self(sprintf('A run that is "%s" cannot %s.', $from->value, $action));
    }
}
