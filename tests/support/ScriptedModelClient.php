<?php

declare(strict_types=1);

namespace WPAgents\Tests\Support;

use LogicException;
use WPAgents\Contracts\Model\ModelException;
use WPAgents\Contracts\Model\ModelRequest;
use WPAgents\Contracts\Model\ModelResponse;
use WPAgents\Contracts\ModelClient;
use WPAgents\Domain\Conversation\ToolCall;

/**
 * Replies with pre-written responses, in order, and records every request.
 */
final class ScriptedModelClient implements ModelClient
{
    /** @var list<ModelResponse|ModelException> */
    private array $script = [];

    /** @var list<ModelRequest> */
    public array $requests = [];

    public function replyWithText(string $text, int $tokensIn = 10, int $tokensOut = 5): self
    {
        $this->script[] = new ModelResponse($text, [], $tokensIn, $tokensOut);

        return $this;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function replyWithToolCall(string $toolName, array $input = [], string $id = ''): self
    {
        return $this->replyWithToolCalls([[$toolName, $input, $id]]);
    }

    /**
     * @param list<array{0: string, 1?: array<string, mixed>, 2?: string}> $calls
     */
    public function replyWithToolCalls(array $calls, string $text = ''): self
    {
        $toolCalls = [];
        foreach ($calls as $index => $call) {
            $id = ($call[2] ?? '') !== '' ? $call[2] : 'call_' . (count($this->script) + 1) . '_' . $index;
            $toolCalls[] = new ToolCall($id, $call[0], $call[1] ?? []);
        }

        $this->script[] = new ModelResponse($text, $toolCalls, 10, 5);

        return $this;
    }

    public function failWith(string $message): self
    {
        $this->script[] = new ModelException($message);

        return $this;
    }

    public function generate(ModelRequest $request): ModelResponse
    {
        $this->requests[] = $request;

        $next = array_shift($this->script);
        if ($next === null) {
            throw new LogicException('The scripted model was called more times than expected.');
        }

        if ($next instanceof ModelException) {
            throw $next;
        }

        return $next;
    }
}
