<?php

declare(strict_types=1);

namespace WPAgents\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPAgents\Domain\Ability\Risk;
use WPAgents\Domain\Agent\ApprovalMode;

final class ApprovalModeTest extends TestCase
{
    /**
     * @return array<string, array{0: ApprovalMode, 1: Risk, 2: bool}>
     */
    public static function cases(): array
    {
        return [
            'writes mode: read is free' => [ApprovalMode::Writes, Risk::Read, false],
            'writes mode: write needs approval' => [ApprovalMode::Writes, Risk::Write, true],
            'writes mode: destructive needs approval' => [ApprovalMode::Writes, Risk::Destructive, true],
            'writes mode: external needs approval' => [ApprovalMode::Writes, Risk::External, true],
            'destructive mode: read is free' => [ApprovalMode::Destructive, Risk::Read, false],
            'destructive mode: write is free' => [ApprovalMode::Destructive, Risk::Write, false],
            'destructive mode: destructive needs approval' => [ApprovalMode::Destructive, Risk::Destructive, true],
            'destructive mode: external needs approval' => [ApprovalMode::Destructive, Risk::External, true],
            'never mode: nothing needs approval' => [ApprovalMode::Never, Risk::External, false],
        ];
    }

    #[DataProvider('cases')]
    public function testRequiresApproval(ApprovalMode $mode, Risk $risk, bool $expected): void
    {
        $this->assertSame($expected, $mode->requiresApproval($risk));
    }

    public function testUnknownValueFallsBackToTheSafestMode(): void
    {
        $this->assertSame(ApprovalMode::Writes, ApprovalMode::fromString('nonsense'));
    }
}
