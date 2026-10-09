<?php

declare(strict_types=1);

namespace AgentStudio\Application\Execute;

use Throwable;
use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Contracts\Clock;
use AgentStudio\Contracts\Identity;
use AgentStudio\Contracts\RecursionGuard;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Conversation\Message;
use AgentStudio\Domain\Conversation\ToolCall;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\StepKind;

/**
 * Carries out one tool call and records the result in the run.
 */
final class ToolExecutor
{
    private const MAX_RESULT_CHARS = 20000;
    private const MAX_PREVIEW_CHARS = 1500;

    public function __construct(
        private readonly AbilityGateway $abilities,
        private readonly Identity $identity,
        private readonly RecursionGuard $guard,
        private readonly Clock $clock,
    ) {
    }

    public function execute(Run $run, Agent $agent, ToolRegistry $registry, ToolCall $call): void
    {
        $now = $this->clock->now();

        if ($registry->isLoadSkillTool($call->name)) {
            $this->loadSkill($run, $registry, $call);

            return;
        }

        $info = $registry->ability($call->name);
        if ($info === null) {
            $this->answer(
                $run,
                $call,
                ['ok' => false, 'error' => sprintf('The tool "%s" is not available to you.', $call->name)]
            );

            return;
        }

        $run->addStep(StepKind::ToolCall, $info->name, ['input' => $call->input], $now);

        try {
            $result = $this->guard->run(
                fn () => $this->identity->runAs(
                    $agent->runAsUserId,
                    fn () => $this->abilities->execute($info->name, $call->input)
                )
            );
        } catch (Throwable $e) {
            $this->answer($run, $call, ['ok' => false, 'error' => 'The ability failed: ' . $e->getMessage()]);

            return;
        }

        $this->answer(
            $run,
            $call,
            $result->ok ? ['ok' => true, 'result' => $result->data] : ['ok' => false, 'error' => $result->error]
        );
    }

    /**
     * Answers a call the administrator rejected, so the model can adapt.
     */
    public function reject(Run $run, ToolCall $call, string $reason): void
    {
        $this->answer($run, $call, ['ok' => false, 'error' => $reason]);
    }

    private function loadSkill(Run $run, ToolRegistry $registry, ToolCall $call): void
    {
        $slug = is_string($call->input['slug'] ?? null) ? $call->input['slug'] : '';
        $skill = $registry->skill($slug);

        $run->addStep(StepKind::ToolCall, 'load_skill', ['input' => $call->input], $this->clock->now());

        if ($skill === null) {
            $this->answer($run, $call, ['ok' => false, 'error' => sprintf('You do not have a skill called "%s".', $slug)]);

            return;
        }

        $this->answer($run, $call, ['ok' => true, 'result' => ['name' => $skill->name, 'instructions' => $skill->body]]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function answer(Run $run, ToolCall $call, array $payload): void
    {
        $content = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        $content = $content === false ? '{"ok":false,"error":"The result could not be encoded."}' : $content;

        if (strlen($content) > self::MAX_RESULT_CHARS) {
            $content = substr($content, 0, self::MAX_RESULT_CHARS) . '…[result truncated]';
        }

        $run->appendMessage(Message::tool($call->id, $call->name, $content));
        $run->addStep(
            StepKind::ToolResult,
            ($payload['ok'] ?? false) === true ? 'ok' : 'error',
            ['preview' => strlen($content) > self::MAX_PREVIEW_CHARS ? substr($content, 0, self::MAX_PREVIEW_CHARS) . '…' : $content],
            $this->clock->now()
        );
    }
}
