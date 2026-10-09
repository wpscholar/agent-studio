<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Application\Dispatch\DispatchOutcome;
use AgentStudio\Domain\Agent\AgentStatus;
use AgentStudio\Domain\Agent\Trigger;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\Tests\Support\TestWorld;

final class DispatchTriggerHandlerTest extends TestCase
{
    private TestWorld $world;

    protected function setUp(): void
    {
        $this->world = new TestWorld();
    }

    public function testQueuesARunForEachMatchingAgent(): void
    {
        $a = $this->world->agent();
        $b = $this->world->agent();
        $this->world->agent(triggers: [new Trigger(TriggerType::UserRegistered)]);

        $result = $this->world->dispatch->handle($this->publishEvent(10));

        $this->assertCount(2, $result->queuedRunIds());
        $this->assertSame(DispatchOutcome::Queued, $result->forAgent($a->id)?->outcome);
        $this->assertSame(DispatchOutcome::Queued, $result->forAgent($b->id)?->outcome);
        $this->assertCount(2, $this->world->queue->jobs);
    }

    public function testStoresTheEventPayloadOnTheRun(): void
    {
        $this->world->agent();

        $result = $this->world->dispatch->handle($this->publishEvent(10));

        $run = $this->world->runs->find($result->queuedRunIds()[0]);
        $this->assertSame(10, $run?->input()['post_id']);
        $this->assertSame(RunStatus::Queued, $run?->status());
    }

    public function testIgnoresPausedAgentsAndNonMatchingEvents(): void
    {
        $this->world->agent(status: AgentStatus::Paused);
        $this->world->agent(triggers: [new Trigger(TriggerType::PostStatus, ['to' => 'pending'])]);

        $result = $this->world->dispatch->handle($this->publishEvent(10));

        $this->assertSame([], $result->queuedRunIds());
        $this->assertSame([], $this->world->queue->jobs);
    }

    public function testTheSameEventIsOnlyHandledOnceInsideTheWindow(): void
    {
        $agent = $this->world->agent();

        $first = $this->world->dispatch->handle($this->publishEvent(10));
        $second = $this->world->dispatch->handle($this->publishEvent(10));

        $this->assertCount(1, $first->queuedRunIds());
        $this->assertSame(DispatchOutcome::Duplicate, $second->forAgent($agent->id)?->outcome);
        $this->assertSame($first->queuedRunIds()[0], $second->forAgent($agent->id)?->runId);
        $this->assertCount(1, $this->world->queue->jobs);
    }

    public function testTheSameEventIsHandledAgainAfterTheWindow(): void
    {
        $this->world->agent();

        $this->world->dispatch->handle($this->publishEvent(10));
        $this->world->clock->advance(61);
        $second = $this->world->dispatch->handle($this->publishEvent(10));

        $this->assertCount(1, $second->queuedRunIds());
    }

    public function testRateLimitSkipsExtraRunsAndRecordsWhy(): void
    {
        $agent = $this->world->agent(maxRunsPerHour: 2);

        $this->world->dispatch->handle($this->publishEvent(1));
        $this->world->dispatch->handle($this->publishEvent(2));
        $third = $this->world->dispatch->handle($this->publishEvent(3));

        $dispatch = $third->forAgent($agent->id);
        $this->assertSame(DispatchOutcome::RateLimited, $dispatch?->outcome);
        $this->assertCount(2, $this->world->queue->jobs);

        $skipped = $this->world->runs->find((int) $dispatch?->runId);
        $this->assertSame(RunStatus::Skipped, $skipped?->status());
        $this->assertStringContainsString('Rate limit', (string) $skipped?->error());
    }

    public function testSkippedRunsDoNotKeepTheLimitTripped(): void
    {
        $this->world->agent(maxRunsPerHour: 1);

        $this->world->dispatch->handle($this->publishEvent(1));
        $this->world->dispatch->handle($this->publishEvent(2));
        $this->world->dispatch->handle($this->publishEvent(3));
        $this->world->clock->advance(3601);
        $later = $this->world->dispatch->handle($this->publishEvent(4));

        $this->assertCount(1, $later->queuedRunIds());
    }

    public function testEventsCausedByAnAgentAreSuppressed(): void
    {
        $this->world->agent();

        $result = $this->world->guard->run(fn () => $this->world->dispatch->handle($this->publishEvent(10)));

        $this->assertTrue($result->suppressed);
        $this->assertSame([], $this->world->queue->jobs);
    }

    public function testManualRunsIgnoreStatusFiltersAndLimits(): void
    {
        $agent = $this->world->agent(
            triggers: [new Trigger(TriggerType::Webhook)],
            status: AgentStatus::Paused,
            maxRunsPerHour: 1
        );

        $one = $this->world->dispatch->handle(TriggerEvent::targeted(TriggerType::Manual, $agent->id));
        $two = $this->world->dispatch->handle(TriggerEvent::targeted(TriggerType::Manual, $agent->id));

        $this->assertCount(1, $one->queuedRunIds());
        $this->assertCount(1, $two->queuedRunIds());
    }

    public function testTargetedWebhookOnlyReachesItsAgent(): void
    {
        $target = $this->world->agent(triggers: [new Trigger(TriggerType::Webhook)]);
        $this->world->agent(triggers: [new Trigger(TriggerType::Webhook)]);

        $result = $this->world->dispatch->handle(
            TriggerEvent::targeted(TriggerType::Webhook, $target->id, ['a' => 1], 'webhook:abc')
        );

        $this->assertCount(1, $result->dispatches);
        $this->assertSame($target->id, $result->dispatches[0]->agentId);
    }

    public function testWebhookDeliveryIsIdempotent(): void
    {
        $agent = $this->world->agent(triggers: [new Trigger(TriggerType::Webhook)]);
        $event = TriggerEvent::targeted(TriggerType::Webhook, $agent->id, ['a' => 1], 'webhook:delivery-1');

        $first = $this->world->dispatch->handle($event);
        $this->world->clock->advance(3600);
        $retry = $this->world->dispatch->handle($event);

        $this->assertCount(1, $first->queuedRunIds());
        $this->assertSame(DispatchOutcome::Duplicate, $retry->forAgent($agent->id)?->outcome);
    }

    public function testTargetingAnUnknownAgentDoesNothing(): void
    {
        $result = $this->world->dispatch->handle(TriggerEvent::targeted(TriggerType::Webhook, 999));

        $this->assertSame([], $result->dispatches);
    }

    private function publishEvent(int $postId): TriggerEvent
    {
        return new TriggerEvent(
            TriggerType::PostStatus,
            ['post_id' => $postId, 'post_type' => 'post', 'new_status' => 'publish', 'old_status' => 'draft'],
            sprintf('post:%d:draft->publish', $postId)
        );
    }
}
