<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use RuntimeException;

/**
 * A tiny service container: factories registered by class name, built once on first use.
 */
final class Container
{
    /** @var array<string, callable(self): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /**
     * @template T of object
     *
     * @param class-string<T>    $id
     * @param callable(self): T  $factory
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public function get(string $id): object
    {
        if (! isset($this->instances[$id])) {
            if (! isset($this->factories[$id])) {
                throw new RuntimeException(sprintf('Nothing is registered for %s.', $id)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; a developer error.
            }

            $this->instances[$id] = ($this->factories[$id])($this);
        }

        $instance = $this->instances[$id];
        if (! $instance instanceof $id) {
            throw new RuntimeException(sprintf('The service for %s has the wrong type.', $id)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; a developer error.
        }

        return $instance;
    }
}
