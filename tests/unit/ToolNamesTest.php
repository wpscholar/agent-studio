<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Domain\Ability\ToolNames;

final class ToolNamesTest extends TestCase
{
    public function testSlashBecomesDoubleUnderscore(): void
    {
        $this->assertSame('agent-studio__get-post', ToolNames::forAbility('agent-studio/get-post'));
    }

    public function testResultMatchesProviderRules(): void
    {
        $name = ToolNames::forAbility('some.vendor/odd name!' . str_repeat('x', 100));

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{1,64}$/', $name);
    }

    public function testLongNamesStayUnique(): void
    {
        $a = ToolNames::forAbility('vendor/' . str_repeat('a', 80) . '-one');
        $b = ToolNames::forAbility('vendor/' . str_repeat('a', 80) . '-two');

        $this->assertNotSame($a, $b);
        $this->assertLessThanOrEqual(ToolNames::MAX_LENGTH, strlen($a));
    }
}
