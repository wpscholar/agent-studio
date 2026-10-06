<?php

declare(strict_types=1);

namespace WPAgents\Tests\Support;

use WPAgents\Application\Approval\ResolveApprovalHandler;
use WPAgents\Application\Dispatch\DispatchTriggerHandler;
use WPAgents\Application\Execute\ExecuteRunHandler;
use WPAgents\Application\Execute\ToolExecutor;
use WPAgents\Application\Execute\ToolRegistryFactory;
use WPAgents\Application\Prompt\PromptBuilder;
use WPAgents\Application\Run\CancelRunHandler;
use WPAgents\Domain\Ability\Risk;
use WPAgents\Domain\Agent\Agent;
use WPAgents\Domain\Agent\AgentStatus;
use WPAgents\Domain\Agent\ApprovalMode;
use WPAgents\Domain\Agent\Trigger;
use WPAgents\Domain\Agent\TriggerType;
use WPAgents\Infrastructure\ProcessRecursionGuard;

/**
 * Wires the whole Application layer to in-memory fakes.
 */
final class TestWorld
{
    public const RUN_AS_USER = 7;

    public InMemoryAgentRepository $agents;
    public InMemoryRunRepository $runs;
    public InMemorySkillRepository $skills;
    public FakeAbilityGateway $abilities;
    public ScriptedModelClient $model;
    public ArrayJobQueue $queue;
    public FixedClock $clock;
    public SpyNotifier $notifier;
    public RecordingIdentity $identity;
    public ProcessRecursionGuard $guard;
    public ExecuteRunHandler $execute;
    public DispatchTriggerHandler $dispatch;
    public ResolveApprovalHandler $approvals;
    public CancelRunHandler $cancel;

    private int $nextAgentId = 1;

    public function __construct()
    {
        $this->agents = new InMemoryAgentRepository();
        $this->runs = new InMemoryRunRepository();
        $this->skills = new InMemorySkillRepository();
        $this->abilities = new FakeAbilityGateway();
        $this->model = new ScriptedModelClient();
        $this->queue = new ArrayJobQueue();
        $this->clock = new FixedClock();
        $this->notifier = new SpyNotifier();
        $this->identity = new RecordingIdentity();
        $this->guard = new ProcessRecursionGuard();

        $this->abilities->register('test/read-thing', Risk::Read);
        $this->abilities->register('test/write-thing', Risk::Write);
        $this->abilities->register('test/delete-thing', Risk::Destructive);
        $this->abilities->register('test/send-thing', Risk::External);

        $registries = new ToolRegistryFactory($this->abilities, $this->skills);
        $executor = new ToolExecutor($this->abilities, $this->identity, $this->guard, $this->clock);

        $this->execute = new ExecuteRunHandler(
            $this->agents,
            $this->runs,
            $registries,
            $this->model,
            $executor,
            new PromptBuilder(),
            $this->notifier,
            $this->identity,
            $this->guard,
            $this->clock
        );
        $this->dispatch = new DispatchTriggerHandler($this->agents, $this->runs, $this->queue, $this->guard, $this->clock);
        $this->approvals = new ResolveApprovalHandler($this->agents, $this->runs, $registries, $executor, $this->queue, $this->clock);
        $this->cancel = new CancelRunHandler($this->runs, $this->clock);
    }

    /**
     * Creates an active agent that can use every test ability.
     *
     * @param list<Trigger>|null $triggers
     * @param list<string>|null  $abilities
     * @param list<string>       $skills
     */
    public function agent(
        ?array $triggers = null,
        ?array $abilities = null,
        ApprovalMode $mode = ApprovalMode::Writes,
        AgentStatus $status = AgentStatus::Active,
        int $maxSteps = 10,
        int $maxRunsPerHour = 30,
        int $runAs = self::RUN_AS_USER,
        array $skills = [],
    ): Agent {
        $agent = new Agent(
            $this->nextAgentId++,
            'Test agent',
            'Do the job.',
            $status,
            $triggers ?? [new Trigger(TriggerType::PostStatus, ['to' => 'publish'])],
            $abilities ?? ['test/read-thing', 'test/write-thing', 'test/delete-thing', 'test/send-thing'],
            $skills,
            $mode,
            $runAs,
            $maxSteps,
            $maxRunsPerHour
        );
        $this->agents->add($agent);

        return $agent;
    }

    /**
     * Executes every queued job, including jobs queued while draining.
     */
    public function drain(): void
    {
        $guard = 0;
        while ($this->queue->jobs !== [] && $guard++ < 50) {
            $runId = array_shift($this->queue->jobs);
            $this->execute->handle($runId);
        }
    }
}
