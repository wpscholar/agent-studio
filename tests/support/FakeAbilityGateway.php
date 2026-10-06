<?php

declare(strict_types=1);

namespace WPAgents\Tests\Support;

use WPAgents\Contracts\Ability\AbilityResult;
use WPAgents\Contracts\AbilityGateway;
use WPAgents\Domain\Ability\AbilityInfo;
use WPAgents\Domain\Ability\Risk;

final class FakeAbilityGateway implements AbilityGateway
{
    /** @var array<string, AbilityInfo> */
    private array $abilities = [];

    /** @var array<string, callable(array<string, mixed>): AbilityResult> */
    private array $handlers = [];

    /** @var list<array{name: string, input: array<string, mixed>}> */
    public array $executed = [];

    /**
     * @param callable(array<string, mixed>): AbilityResult|null $handler
     */
    public function register(string $name, Risk $risk, ?callable $handler = null): void
    {
        $this->abilities[$name] = new AbilityInfo(
            $name,
            ucfirst(str_replace(['test/', '-'], ['', ' '], $name)),
            'Test ability ' . $name,
            'test',
            $risk,
            ['type' => 'object', 'properties' => ['value' => ['type' => 'string']]]
        );
        $this->handlers[$name] = $handler ?? static fn (array $input): AbilityResult => AbilityResult::success(['echo' => $input]);
    }

    public function available(): array
    {
        return array_values($this->abilities);
    }

    public function execute(string $abilityName, array $input): AbilityResult
    {
        $this->executed[] = ['name' => $abilityName, 'input' => $input];

        return ($this->handlers[$abilityName])($input);
    }
}
