<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Domain\Conversation\Message;
use AgentStudio\Domain\Conversation\ToolCall;

final class ToolCallTest extends TestCase
{
    public function testTheModelsSignatureSurvivesStorage(): void
    {
        $message = Message::assistant('', [new ToolCall('c1', 'agent-studio__get-user', ['id' => 1], 'sig-abc')]);

        $stored = json_decode((string) json_encode($message->toArray()), true);
        $restored = Message::fromArray($stored);

        $this->assertSame('sig-abc', $restored->toolCalls[0]->signature);
        $this->assertSame(['id' => 1], $restored->toolCalls[0]->input);
    }

    public function testCallsWithoutASignatureStayAsBefore(): void
    {
        $call = new ToolCall('c1', 'a__b', ['k' => 'v']);

        $this->assertSame(['id' => 'c1', 'name' => 'a__b', 'input' => ['k' => 'v']], $call->toArray());
        $this->assertSame('', ToolCall::fromArray(['id' => 'c1', 'name' => 'a__b'])->signature, 'Runs saved before signatures were kept still load.');
    }
}
