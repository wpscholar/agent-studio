<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Domain\Conversation\Message;
use AgentStudio\Domain\Conversation\ToolCall;
use AgentStudio\Domain\Conversation\Transcript;

final class TranscriptTest extends TestCase
{
    public function testNoCallsWhenTheModelOnlySpoke(): void
    {
        $messages = [Message::user('hi'), Message::assistant('hello')];

        $this->assertSame([], Transcript::unansweredCalls($messages));
    }

    public function testFindsCallsWithoutResults(): void
    {
        $messages = [
            Message::user('hi'),
            Message::assistant('', [new ToolCall('a', 'one'), new ToolCall('b', 'two'), new ToolCall('c', 'three')]),
            Message::tool('a', 'one', '{}'),
        ];

        $unanswered = Transcript::unansweredCalls($messages);

        $this->assertSame(['b', 'c'], array_map(static fn (ToolCall $c): string => $c->id, $unanswered));
    }

    public function testOnlyTheLatestAssistantMessageCounts(): void
    {
        $messages = [
            Message::assistant('', [new ToolCall('old', 'one')]),
            Message::tool('old', 'one', '{}'),
            Message::assistant('done'),
        ];

        $this->assertSame([], Transcript::unansweredCalls($messages));
    }
}
