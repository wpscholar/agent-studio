<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Application\Approval\ApprovalTokens;
use AgentStudio\Domain\Ability\Risk;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Run\PendingApproval;
use AgentStudio\Domain\Run\Run;

final class ApprovalTokensTest extends TestCase
{
    private function waitingRun(int $id, string $toolCallId = 'call_1'): Run
    {
        $run = new Run($id, 1, TriggerType::Manual, [], 0);
        $run->start(0);
        $run->awaitApproval(new PendingApproval($toolCallId, 'tool', 'site/do', [], Risk::Write, 0), 0);

        return $run;
    }

    public function testAButtonCarriesTheRunAndDecisionAndFitsTelegram(): void
    {
        $tokens = new ApprovalTokens('key');
        $run = $this->waitingRun(42);

        $value = $tokens->button($run, true);
        $parsed = ApprovalTokens::parseButton($value);

        $this->assertLessThanOrEqual(64, strlen($value));
        $this->assertSame(42, $parsed['run'] ?? null);
        $this->assertTrue($parsed['approve'] ?? false);
        $this->assertTrue($tokens->verifyButton($run, true, $parsed['signature']));
        $this->assertFalse($tokens->verifyButton($run, false, $parsed['signature']), 'An approve button cannot reject.');
    }

    public function testAButtonOnlyWorksForTheActionItWasSentFor(): void
    {
        $tokens = new ApprovalTokens('key');
        $signature = ApprovalTokens::parseButton($tokens->button($this->waitingRun(42, 'call_1'), true))['signature'] ?? '';

        $this->assertFalse($tokens->verifyButton($this->waitingRun(42, 'call_2'), true, $signature));
        $this->assertFalse((new ApprovalTokens('other'))->verifyButton($this->waitingRun(42, 'call_1'), true, $signature));
    }

    public function testLinksAreBoundToTheChannelAndPerson(): void
    {
        $tokens = new ApprovalTokens('key');
        $run = $this->waitingRun(7);
        $token = $tokens->link($run, true, 3, 1);

        $this->assertTrue($tokens->verifyLink($run, true, 3, 1, $token));
        $this->assertFalse($tokens->verifyLink($run, true, 4, 1, $token));
        $this->assertFalse($tokens->verifyLink($run, true, 3, 2, $token));
        $this->assertFalse($tokens->verifyLink($run, false, 3, 1, $token));
    }

    public function testRejectsValuesItDidNotMake(): void
    {
        $this->assertNull(ApprovalTokens::parseButton('ap:abc:123'));
        $this->assertNull(ApprovalTokens::parseButton('something else'));
    }
}
