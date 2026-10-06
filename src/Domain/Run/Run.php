<?php

declare(strict_types=1);

namespace WPAgents\Domain\Run;

use WPAgents\Domain\Agent\TriggerType;
use WPAgents\Domain\Conversation\Message;

/**
 * One execution of an agent, from trigger to outcome, including the full log.
 */
final class Run
{
    private RunStatus $status = RunStatus::Queued;

    /** @var list<Message> */
    private array $transcript = [];

    /** @var list<RunStep> */
    private array $steps = [];

    private ?PendingApproval $pending = null;
    private string $output = '';
    private string $error = '';
    private int $tokensIn = 0;
    private int $tokensOut = 0;
    private int $startedAt = 0;
    private int $finishedAt = 0;

    /**
     * @param array<string, mixed> $input The trigger payload. Untrusted.
     */
    public function __construct(
        private ?int $id,
        private readonly int $agentId,
        private readonly TriggerType $triggerType,
        private readonly array $input,
        private readonly int $createdAt,
        private readonly string $dedupeKey = '',
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function assignId(int $id): void
    {
        $this->id = $id;
    }

    public function agentId(): int
    {
        return $this->agentId;
    }

    public function triggerType(): TriggerType
    {
        return $this->triggerType;
    }

    /**
     * @return array<string, mixed>
     */
    public function input(): array
    {
        return $this->input;
    }

    public function createdAt(): int
    {
        return $this->createdAt;
    }

    public function dedupeKey(): string
    {
        return $this->dedupeKey;
    }

    public function status(): RunStatus
    {
        return $this->status;
    }

    public function output(): string
    {
        return $this->output;
    }

    public function error(): string
    {
        return $this->error;
    }

    public function tokensIn(): int
    {
        return $this->tokensIn;
    }

    public function tokensOut(): int
    {
        return $this->tokensOut;
    }

    public function startedAt(): int
    {
        return $this->startedAt;
    }

    public function finishedAt(): int
    {
        return $this->finishedAt;
    }

    public function pendingApproval(): ?PendingApproval
    {
        return $this->pending;
    }

    /**
     * @return list<Message>
     */
    public function transcript(): array
    {
        return $this->transcript;
    }

    /**
     * @return list<RunStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * How many times the model has been called so far.
     */
    public function modelCalls(): int
    {
        $count = 0;
        foreach ($this->steps as $step) {
            if ($step->kind === StepKind::Model) {
                $count++;
            }
        }

        return $count;
    }

    public function start(int $now): void
    {
        if ($this->status !== RunStatus::Queued) {
            throw InvalidRunTransition::between($this->status, 'start');
        }

        $this->status = RunStatus::Running;
        $this->startedAt = $this->startedAt === 0 ? $now : $this->startedAt;
    }

    public function awaitApproval(PendingApproval $pending, int $now): void
    {
        if ($this->status !== RunStatus::Running) {
            throw InvalidRunTransition::between($this->status, 'wait for approval');
        }

        $this->status = RunStatus::AwaitingApproval;
        $this->pending = $pending;
        $this->addStep(
            StepKind::Approval,
            'Approval requested',
            ['ability' => $pending->abilityName, 'risk' => $pending->risk->value, 'input' => $pending->input],
            $now
        );
    }

    /**
     * Puts a run that was waiting for approval back in the queue.
     */
    public function resume(): void
    {
        if ($this->status !== RunStatus::AwaitingApproval) {
            throw InvalidRunTransition::between($this->status, 'resume');
        }

        $this->status = RunStatus::Queued;
        $this->pending = null;
    }

    public function complete(string $output, int $now): void
    {
        if ($this->status !== RunStatus::Running) {
            throw InvalidRunTransition::between($this->status, 'complete');
        }

        $this->status = RunStatus::Completed;
        $this->output = $output;
        $this->finishedAt = $now;
    }

    public function fail(string $error, int $now): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidRunTransition::between($this->status, 'fail');
        }

        $this->status = RunStatus::Failed;
        $this->error = $error;
        $this->pending = null;
        $this->finishedAt = $now;
        $this->addStep(StepKind::Error, $error, [], $now);
    }

    public function cancel(int $now): void
    {
        if ($this->status !== RunStatus::Queued && $this->status !== RunStatus::AwaitingApproval) {
            throw InvalidRunTransition::between($this->status, 'be cancelled');
        }

        $this->status = RunStatus::Cancelled;
        $this->pending = null;
        $this->finishedAt = $now;
        $this->addStep(StepKind::Note, 'Run cancelled', [], $now);
    }

    /**
     * Marks a run that was never started, with the reason.
     */
    public function skip(string $reason, int $now): void
    {
        if ($this->status !== RunStatus::Queued) {
            throw InvalidRunTransition::between($this->status, 'be skipped');
        }

        $this->status = RunStatus::Skipped;
        $this->error = $reason;
        $this->finishedAt = $now;
        $this->addStep(StepKind::Note, $reason, [], $now);
    }

    public function appendMessage(Message $message): void
    {
        $this->transcript[] = $message;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addStep(StepKind $kind, string $label, array $data, int $now): void
    {
        $this->steps[] = new RunStep($kind, $label, $data, $now);
    }

    public function addUsage(int $tokensIn, int $tokensOut): void
    {
        $this->tokensIn += max(0, $tokensIn);
        $this->tokensOut += max(0, $tokensOut);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agentId,
            'trigger_type' => $this->triggerType->value,
            'status' => $this->status->value,
            'dedupe_key' => $this->dedupeKey,
            'input' => $this->input,
            'transcript' => array_map(static fn (Message $m): array => $m->toArray(), $this->transcript),
            'steps' => array_map(static fn (RunStep $s): array => $s->toArray(), $this->steps),
            'pending' => $this->pending?->toArray(),
            'output' => $this->output,
            'error' => $this->error,
            'tokens_in' => $this->tokensIn,
            'tokens_out' => $this->tokensOut,
            'created_at' => $this->createdAt,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $input = $data['input'] ?? [];
        $run = new self(
            is_numeric($data['id'] ?? null) ? (int) $data['id'] : null,
            is_numeric($data['agent_id'] ?? null) ? (int) $data['agent_id'] : 0,
            TriggerType::tryFrom(is_string($data['trigger_type'] ?? null) ? $data['trigger_type'] : '')
                ?? TriggerType::Manual,
            is_array($input) ? $input : [],
            is_numeric($data['created_at'] ?? null) ? (int) $data['created_at'] : 0,
            is_scalar($data['dedupe_key'] ?? null) ? (string) $data['dedupe_key'] : '',
        );

        $run->status = RunStatus::fromString(is_string($data['status'] ?? null) ? $data['status'] : '');
        $run->output = is_scalar($data['output'] ?? null) ? (string) $data['output'] : '';
        $run->error = is_scalar($data['error'] ?? null) ? (string) $data['error'] : '';
        $run->tokensIn = is_numeric($data['tokens_in'] ?? null) ? (int) $data['tokens_in'] : 0;
        $run->tokensOut = is_numeric($data['tokens_out'] ?? null) ? (int) $data['tokens_out'] : 0;
        $run->startedAt = is_numeric($data['started_at'] ?? null) ? (int) $data['started_at'] : 0;
        $run->finishedAt = is_numeric($data['finished_at'] ?? null) ? (int) $data['finished_at'] : 0;

        if (is_array($data['transcript'] ?? null)) {
            foreach ($data['transcript'] as $message) {
                if (is_array($message)) {
                    $run->transcript[] = Message::fromArray($message);
                }
            }
        }

        if (is_array($data['steps'] ?? null)) {
            foreach ($data['steps'] as $step) {
                if (is_array($step)) {
                    $run->steps[] = RunStep::fromArray($step);
                }
            }
        }

        if (is_array($data['pending'] ?? null)) {
            $run->pending = PendingApproval::fromArray($data['pending']);
        }

        return $run;
    }
}
