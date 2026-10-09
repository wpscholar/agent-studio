<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Application\Dispatch\DispatchOutcome;
use AgentStudio\Application\Dispatch\DispatchTriggerHandler;
use AgentStudio\Application\Prompt\PromptBuilder;
use AgentStudio\Application\Triggers\TriggerDefinitions;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\AgentStatus;
use AgentStudio\Domain\Agent\Trigger;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Tests\Support\ArrayJobQueue;
use AgentStudio\Tests\Support\FixedClock;
use AgentStudio\Tests\Support\InMemoryAgentRepository;
use AgentStudio\Tests\Support\InMemoryRunRepository;
use AgentStudio\Tests\Support\OrderPlacedTrigger;
use AgentStudio\Infrastructure\ProcessRecursionGuard;

final class CustomTriggerTest extends TestCase
{
    private static function agent(int $id, array $config): Agent
    {
        return new Agent($id, 'A' . $id, 'x', AgentStatus::Active, [new Trigger(TriggerType::Custom, $config)]);
    }

    private static function event(float $total): TriggerEvent
    {
        return new TriggerEvent(TriggerType::Custom, ['trigger' => 'test-shop/order-placed', 'about' => 'Order #7 was placed.', 'order_id' => 7, 'total' => $total]);
    }

    public function testOnlyAgentsWatchingThatEventAndWhoseFiltersMatchStart(): void
    {
        $agents = new InMemoryAgentRepository();
        $agents->add(self::agent(1, ['kind' => 'test-shop/order-placed']));
        $agents->add(self::agent(2, ['kind' => 'test-shop/order-placed', 'min_total' => '200']));
        $agents->add(self::agent(3, ['kind' => 'other/thing']));
        $definitions = new TriggerDefinitions();
        $definitions->register(new OrderPlacedTrigger());

        $handler = new DispatchTriggerHandler($agents, new InMemoryRunRepository(), new ArrayJobQueue(), new ProcessRecursionGuard(), new FixedClock(), $definitions);
        $result = $handler->handle(self::event(150));

        $this->assertSame(DispatchOutcome::Queued, $result->forAgent(1)?->outcome);
        $this->assertSame(DispatchOutcome::Skipped, $result->forAgent(2)?->outcome);
        $this->assertSame(DispatchOutcome::Skipped, $result->forAgent(3)?->outcome, 'Agents watching another event do not start.');
    }

    public function testAnEventFromAPluginThatIsGoneStartsNothing(): void
    {
        $agents = new InMemoryAgentRepository();
        $agents->add(self::agent(1, ['kind' => 'test-shop/order-placed']));

        $handler = new DispatchTriggerHandler($agents, new InMemoryRunRepository(), new ArrayJobQueue(), new ProcessRecursionGuard(), new FixedClock(), new TriggerDefinitions());

        $this->assertSame(DispatchOutcome::Skipped, $handler->handle(self::event(150))->forAgent(1)?->outcome);
    }

    public function testTheAgentIsToldWhatHappenedInThePluginsWords(): void
    {
        $message = (new PromptBuilder())->triggerMessage(new Run(1, 1, TriggerType::Custom, ['trigger' => 'test-shop/order-placed', 'about' => 'Order #7 was placed.'], 0));

        $this->assertStringStartsWith('Order #7 was placed.', $message);
    }

    public function testTriggerKeysNeedANamespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TriggerDefinitions())->register(new class extends \stdClass implements \AgentStudio\Contracts\Trigger\TriggerDefinition {
            public function key(): string
            {
                return 'no-namespace';
            }

            public function label(): string
            {
                return '';
            }

            public function description(): string
            {
                return '';
            }

            public function fields(): array
            {
                return [];
            }

            public function matches(array $config, array $payload): bool
            {
                return true;
            }

            public function about(array $payload): string
            {
                return '';
            }
        });
    }
}
