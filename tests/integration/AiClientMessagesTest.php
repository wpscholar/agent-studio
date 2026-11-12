<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use WP_UnitTestCase;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use AgentStudio\Domain\Conversation\Message as ConversationMessage;
use AgentStudio\Domain\Conversation\ToolCall;
use AgentStudio\Infrastructure\AiClientMessages;

/**
 * Gemini thinking models sign their function calls and reject a conversation
 * that sends an earlier call back without its signature.
 */
final class AiClientMessagesTest extends WP_UnitTestCase
{
    public function testKeepsTheSignatureOfEachToolCallTheModelMakes(): void
    {
        $reply = new Message(MessageRoleEnum::model(), [
            new MessagePart('Looking that up.'),
            new MessagePart(new FunctionCall('c1', 'agent-studio__get-user', ['id' => 1]), null, 'sig-first'),
            // In parallel calls Gemini signs only the first one.
            new MessagePart(new FunctionCall('c2', 'agent-studio__get-post', ['id' => 2])),
        ]);

        [$text, $calls] = (new AiClientMessages())->fromAiClient($reply);

        $this->assertSame('Looking that up.', $text);
        $this->assertSame('sig-first', $calls[0]->signature);
        $this->assertSame('', $calls[1]->signature);
        $this->assertSame(['id' => 1], $calls[0]->input);
    }

    public function testSendsTheSignatureBackWithTheHistory(): void
    {
        $history = (new AiClientMessages())->toHistory([
            ConversationMessage::user('Who is user 1?'),
            ConversationMessage::assistant('', [
                new ToolCall('c1', 'agent-studio__get-user', ['id' => 1], 'sig-first'),
                new ToolCall('c2', 'agent-studio__get-post'),
            ]),
        ]);

        $parts = $history[1]->getParts();
        $this->assertSame('sig-first', $parts[0]->getThoughtSignature());
        $this->assertNull($parts[1]->getThoughtSignature());
        $this->assertSame('agent-studio__get-user', $parts[0]->getFunctionCall()?->getName());
    }

    public function testASignedCallSurvivesARoundTripThroughStorage(): void
    {
        $mapper = new AiClientMessages();
        $reply = new Message(MessageRoleEnum::model(), [
            new MessagePart(new FunctionCall('c1', 'agent-studio__get-user', ['id' => 1]), null, 'sig-first'),
        ]);

        [, $calls] = $mapper->fromAiClient($reply);
        $stored = ConversationMessage::fromArray(json_decode((string) wp_json_encode(ConversationMessage::assistant('', $calls)->toArray()), true));
        $history = $mapper->toHistory([$stored]);

        $this->assertSame('sig-first', $history[0]->getParts()[0]->getThoughtSignature());
    }
}
