<?php

declare(strict_types=1);

namespace AgentStudio\Contracts;

/**
 * Runs code as a given site user, so ability permission checks apply to the agent.
 */
interface Identity
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runAs(int $userId, callable $callback): mixed;
}
