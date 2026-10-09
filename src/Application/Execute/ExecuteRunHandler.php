<?php

declare(strict_types=1);

namespace AgentStudio\Application\Execute;

use Throwable;
use AgentStudio\Application\Conversations\ConversationService;
use AgentStudio\Application\Prompt\PromptBuilder;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\Clock;
use AgentStudio\Contracts\Identity;
use AgentStudio\Contracts\Model\ModelException;
use AgentStudio\Contracts\Model\ModelRequest;
use AgentStudio\Contracts\ModelClient;
use AgentStudio\Contracts\Notifier;
use AgentStudio\Contracts\RecursionGuard;
use AgentStudio\Contracts\RunObserver;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Conversation\Message;
use AgentStudio\Domain\Conversation\Transcript;
use AgentStudio\Domain\Run\PendingApproval;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\Domain\Run\StepKind;

/**
 * Runs the agent loop: ask the model, carry out its tool calls, repeat.
 *
 * The loop can stop in three ways: the model finishes, the step limit is hit,
 * or a tool call needs approval. After approval the run is queued again and
 * this handler continues from the saved conversation.
 */
final class ExecuteRunHandler
{
    private const MAX_OUTPUT_TOKENS = 4096;
    private const MAX_LABEL_CHARS = 200;
    private const MAX_STEP_TEXT_CHARS = 4000;

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly ToolRegistryFactory $registries,
        private readonly ModelClient $model,
        private readonly ToolExecutor $executor,
        private readonly PromptBuilder $prompts,
        private readonly Notifier $notifier,
        private readonly Identity $identity,
        private readonly RecursionGuard $guard,
        private readonly Clock $clock,
        private readonly ?ConversationService $conversations = null,
        private readonly ?RunObserver $observer = null,
    ) {
    }

    public function handle(int $runId): void
    {
        $run = $this->runs->claim($runId, $this->clock->now());
        if ($run === null) {
            return;
        }

        $agent = $this->agents->find($run->agentId());

        try {
            if ($agent === null) {
                $run->fail('The agent no longer exists.', $this->clock->now());
            } elseif (! $agent->isActive() && $run->triggerType() !== TriggerType::Manual) {
                $run->fail('The agent was paused before this run started.', $this->clock->now());
            } elseif ($agent->runAsUserId <= 0) {
                $run->fail('The agent has no "run as" user. Edit the agent and choose one.', $this->clock->now());
            } else {
                $this->guard->run(
                    fn () => $this->identity->runAs($agent->runAsUserId, fn () => $this->loop($run, $agent))
                );
            }
        } catch (Throwable $e) {
            if (! $run->status()->isTerminal()) {
                $run->fail('Unexpected error: ' . $e->getMessage(), $this->clock->now());
            }
        }

        $this->runs->save($run);

        if ($this->observer !== null && ($run->status()->isTerminal() || $run->status() === RunStatus::AwaitingApproval)) {
            try {
                $this->observer->runStopped($run);
            } catch (Throwable) {
                // Telling others about the run must never change its outcome.
            }
        }
    }

    private function loop(Run $run, Agent $agent): void
    {
        $registry = $this->registries->forAgent($agent);
        $conversation = $this->conversations?->transcript($run);
        $channel = $conversation === null ? '' : (string) ($run->input()['channel'] ?? 'chat');
        $system = $this->prompts->system($agent, $registry->skills(), $channel !== '' ? $channel : '');

        if ($run->transcript() === []) {
            foreach ($conversation ?? [Message::user($this->prompts->triggerMessage($run))] as $message) {
                $run->appendMessage($message);
            }
        }

        while (true) {
            $unanswered = Transcript::unansweredCalls($run->transcript());

            if ($unanswered !== []) {
                foreach ($unanswered as $call) {
                    $info = $registry->ability($call->name);

                    if ($info !== null && $agent->approvalMode->requiresApproval($info->risk)) {
                        $pending = new PendingApproval(
                            $call->id,
                            $call->name,
                            $info->name,
                            $call->input,
                            $info->risk,
                            $this->clock->now()
                        );
                        $run->awaitApproval($pending, $this->clock->now());
                        $this->runs->save($run);
                        $this->notifier->approvalRequested($agent, $run, $pending);

                        return;
                    }

                    $this->executor->execute($run, $agent, $registry, $call);
                }

                $this->runs->save($run);
                continue;
            }

            if ($run->modelCalls() >= $agent->maxSteps) {
                $run->fail(
                    sprintf('Stopped after %d steps without finishing. Raise the step limit or simplify the task.', $agent->maxSteps),
                    $this->clock->now()
                );

                return;
            }

            try {
                $response = $this->model->generate(
                    new ModelRequest(
                        $system,
                        $run->transcript(),
                        $registry->specs(),
                        $agent->model,
                        self::MAX_OUTPUT_TOKENS,
                        $agent->provider
                    )
                );
            } catch (ModelException $e) {
                $run->fail('Model error: ' . $e->getMessage(), $this->clock->now());

                return;
            }

            $run->addUsage($response->tokensIn, $response->tokensOut);
            $run->appendMessage(Message::assistant($response->text, $response->toolCalls));

            $toolNames = array_map(static fn ($call): string => $call->name, $response->toolCalls);
            $label = trim($response->text) !== ''
                ? mb_substr(trim($response->text), 0, self::MAX_LABEL_CHARS)
                : 'Requested tools: ' . implode(', ', $toolNames);
            $run->addStep(
                StepKind::Model,
                $label,
                [
                    'text' => mb_substr($response->text, 0, self::MAX_STEP_TEXT_CHARS),
                    'tool_calls' => $toolNames,
                    'tokens_in' => $response->tokensIn,
                    'tokens_out' => $response->tokensOut,
                ],
                $this->clock->now()
            );

            if ($response->toolCalls === []) {
                $run->complete(trim($response->text), $this->clock->now());

                return;
            }

            $this->runs->save($run);
        }
    }
}
