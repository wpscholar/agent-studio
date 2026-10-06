<?php

declare(strict_types=1);

namespace WPAgents\Application\Approval;

use WPAgents\Application\Execute\ToolExecutor;
use WPAgents\Application\Execute\ToolRegistryFactory;
use WPAgents\Contracts\AgentRepository;
use WPAgents\Contracts\Clock;
use WPAgents\Contracts\JobQueue;
use WPAgents\Contracts\RunRepository;
use WPAgents\Domain\Conversation\ToolCall;
use WPAgents\Domain\Run\RunStatus;
use WPAgents\Domain\Run\StepKind;

/**
 * Applies a person's decision to a run that is waiting for approval,
 * then puts the run back in the queue so the agent can continue.
 */
final class ResolveApprovalHandler
{
    public const REJECTION_MESSAGE = 'The administrator rejected this action. Do not try it again.';

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly ToolRegistryFactory $registries,
        private readonly ToolExecutor $executor,
        private readonly JobQueue $queue,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return bool False when the run does not exist or is not waiting for approval.
     */
    public function handle(int $runId, bool $approve, int $decidedByUserId): bool
    {
        $run = $this->runs->find($runId);
        if ($run === null || $run->status() !== RunStatus::AwaitingApproval) {
            return false;
        }

        $pending = $run->pendingApproval();
        if ($pending === null) {
            return false;
        }

        $now = $this->clock->now();
        $call = new ToolCall($pending->toolCallId, $pending->toolName, $pending->input);

        $run->addStep(
            StepKind::Approval,
            $approve ? 'Approved' : 'Rejected',
            ['ability' => $pending->abilityName, 'decided_by' => $decidedByUserId],
            $now
        );

        $agent = $this->agents->find($run->agentId());

        if ($approve && $agent !== null) {
            $this->executor->execute($run, $agent, $this->registries->forAgent($agent), $call);
        } else {
            $this->executor->reject(
                $run,
                $call,
                $agent === null ? 'The agent no longer exists.' : self::REJECTION_MESSAGE
            );
        }

        $run->resume();
        $this->runs->save($run);
        $this->queue->enqueueRun((int) $run->id());

        return true;
    }
}
