<?php

declare(strict_types=1);

namespace WPAgents\Contracts\Ability;

final class AbilityResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly mixed $data,
        public readonly string $error,
    ) {
    }

    public static function success(mixed $data): self
    {
        return new self(true, $data, '');
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }
}
