<?php

declare(strict_types=1);

namespace WPAgents\Contracts;

/**
 * Stops agents from triggering themselves.
 *
 * While an agent is acting, events caused by its actions do not start agents.
 */
interface RecursionGuard
{
    public function active(): bool;

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(callable $callback): mixed;
}
