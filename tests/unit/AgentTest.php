<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\AgentStatus;
use AgentStudio\Domain\Agent\Trigger;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;

final class AgentTest extends TestCase
{
    public function testLimitsAreClamped(): void
    {
        $agent = new Agent(1, 'A', 'x', maxSteps: 9999, maxRunsPerHour: -5);

        $this->assertSame(Agent::MAX_STEPS, $agent->maxSteps);
        $this->assertSame(1, $agent->maxRunsPerHour);

        $this->assertSame(Agent::MIN_STEPS, (new Agent(1, 'A', 'x', maxSteps: 0))->maxSteps);
    }

    public function testNewAgentsArePausedByDefault(): void
    {
        $this->assertFalse((new Agent(1, 'A', 'x'))->isActive());
        $this->assertTrue((new Agent(1, 'A', 'x', AgentStatus::Active))->isActive());
    }

    public function testAllowlists(): void
    {
        $agent = new Agent(1, 'A', 'x', abilities: ['a/b'], skills: ['voice']);

        $this->assertTrue($agent->allowsAbility('a/b'));
        $this->assertFalse($agent->allowsAbility('a/c'));
        $this->assertTrue($agent->allowsSkill('voice'));
        $this->assertFalse($agent->allowsSkill('other'));
    }

    public function testMatchesWhenAnyTriggerMatches(): void
    {
        $agent = new Agent(1, 'A', 'x', triggers: [
            new Trigger(TriggerType::UserRegistered),
            new Trigger(TriggerType::PostStatus, ['to' => 'publish']),
        ]);

        $this->assertTrue($agent->matches(new TriggerEvent(TriggerType::PostStatus, ['new_status' => 'publish'])));
        $this->assertFalse($agent->matches(new TriggerEvent(TriggerType::PostStatus, ['new_status' => 'draft'])));
        $this->assertTrue($agent->hasTrigger(TriggerType::UserRegistered));
        $this->assertFalse($agent->hasTrigger(TriggerType::Webhook));
    }
}
