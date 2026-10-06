<?php

declare(strict_types=1);

namespace WPAgents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAgents\Domain\Ability\Risk;
use WPAgents\Domain\Agent\TriggerType;
use WPAgents\Domain\Conversation\Message;
use WPAgents\Domain\Conversation\ToolCall;
use WPAgents\Domain\Run\InvalidRunTransition;
use WPAgents\Domain\Run\PendingApproval;
use WPAgents\Domain\Run\Run;
use WPAgents\Domain\Run\RunStatus;
use WPAgents\Domain\Run\StepKind;

final class RunTest extends TestCase
{
    public function testHappyPath(): void
    {
        $run = $this->newRun();
        $this->assertSame(RunStatus::Queued, $run->status());

        $run->start(101);
        $run->complete('All done', 105);

        $this->assertSame(RunStatus::Completed, $run->status());
        $this->assertSame('All done', $run->output());
        $this->assertSame(101, $run->startedAt());
        $this->assertSame(105, $run->finishedAt());
    }

    public function testApprovalPauseAndResume(): void
    {
        $run = $this->newRun();
        $run->start(101);
        $run->awaitApproval(new PendingApproval('c1', 'a__b', 'a/b', ['x' => 1], Risk::Write, 102), 102);

        $this->assertSame(RunStatus::AwaitingApproval, $run->status());
        $this->assertNotNull($run->pendingApproval());

        $run->resume();

        $this->assertSame(RunStatus::Queued, $run->status());
        $this->assertNull($run->pendingApproval());
    }

    public function testStartedAtSurvivesAResume(): void
    {
        $run = $this->newRun();
        $run->start(101);
        $run->awaitApproval(new PendingApproval('c1', 'a__b', 'a/b', [], Risk::Write, 102), 102);
        $run->resume();
        $run->start(500);

        $this->assertSame(101, $run->startedAt());
    }

    public function testFinishedRunsCannotChangeState(): void
    {
        $run = $this->newRun();
        $run->start(101);
        $run->complete('x', 102);

        $this->expectException(InvalidRunTransition::class);
        $run->fail('late', 103);
    }

    public function testOnlyWaitingRunsCanBeCancelled(): void
    {
        $run = $this->newRun();
        $run->start(101);

        $this->expectException(InvalidRunTransition::class);
        $run->cancel(102);
    }

    public function testQueuedRunCanBeSkippedWithAReason(): void
    {
        $run = $this->newRun();
        $run->skip('Rate limit reached', 101);

        $this->assertSame(RunStatus::Skipped, $run->status());
        $this->assertSame('Rate limit reached', $run->error());
        $this->assertTrue($run->status()->isTerminal());
    }

    public function testModelCallsAreCountedFromTheLog(): void
    {
        $run = $this->newRun();
        $run->addStep(StepKind::Model, 'one', [], 1);
        $run->addStep(StepKind::ToolCall, 'x', [], 2);
        $run->addStep(StepKind::Model, 'two', [], 3);

        $this->assertSame(2, $run->modelCalls());
    }

    public function testSurvivesStorage(): void
    {
        $run = $this->newRun();
        $run->assignId(42);
        $run->start(101);
        $run->appendMessage(Message::user('hello'));
        $run->appendMessage(Message::assistant('calling', [new ToolCall('c1', 'a__b', ['k' => 'v'])]));
        $run->appendMessage(Message::tool('c1', 'a__b', '{"ok":true}'));
        $run->addUsage(10, 4);
        $run->addUsage(5, 1);
        $run->awaitApproval(new PendingApproval('c2', 'a__c', 'a/c', ['z' => true], Risk::Destructive, 103), 103);

        $restored = Run::fromArray(json_decode((string) json_encode($run->toArray()), true));

        $this->assertSame($run->toArray(), $restored->toArray());
        $this->assertSame(42, $restored->id());
        $this->assertSame(15, $restored->tokensIn());
        $this->assertSame(5, $restored->tokensOut());
        $this->assertSame(Risk::Destructive, $restored->pendingApproval()?->risk);
        $this->assertCount(3, $restored->transcript());
        $this->assertSame('c1', $restored->transcript()[1]->toolCalls[0]->id);
    }

    private function newRun(): Run
    {
        return new Run(null, 3, TriggerType::PostStatus, ['post_id' => 9], 100, 'post:9');
    }
}
