<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AgentStudio\Application\Conversations\ConversationService;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Conversation\ChatMessage;
use AgentStudio\Domain\Conversation\ConversationWindow;
use AgentStudio\Domain\Conversation\Message;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Tests\Support\FixedClock;
use AgentStudio\Tests\Support\InMemoryConversationRepository;
use AgentStudio\Tests\Support\ScriptedModelClient;

final class ConversationTest extends TestCase
{
    private InMemoryConversationRepository $repository;
    private ScriptedModelClient $model;
    private FixedClock $clock;
    private ConversationService $service;

    protected function setUp(): void
    {
        $this->repository = new InMemoryConversationRepository();
        $this->model = new ScriptedModelClient();
        $this->clock = new FixedClock();
        $this->service = new ConversationService($this->repository, $this->model, $this->clock, new ConversationWindow(4, 1000, 86400));
    }

    /**
     * @param list<ChatMessage> $messages
     *
     * @return list<string>
     */
    private static function texts(array $messages): array
    {
        return array_map(static fn (ChatMessage $m): string => $m->text, $messages);
    }

    private static function message(int $id, string $text, string $from = ChatMessage::FROM_PERSON): ChatMessage
    {
        return new ChatMessage($id, 1, $from, $text);
    }

    public function testTheWindowKeepsTheNewestMessagesUpToTheCount(): void
    {
        $window = new ConversationWindow(2, 1000);

        $split = $window->split([self::message(1, 'a'), self::message(2, 'b'), self::message(3, 'c')]);

        $this->assertSame(['b', 'c'], self::texts($split['window']));
        $this->assertSame(['a'], self::texts($split['overflow']));
    }

    public function testTheWindowAlsoKeepsUnderTheSizeCapButAlwaysShowsTheNewest(): void
    {
        $window = new ConversationWindow(20, 10);

        $split = $window->split([self::message(1, 'short'), self::message(2, str_repeat('x', 50))]);

        $this->assertSame([str_repeat('x', 50)], self::texts($split['window']));
        $this->assertSame(['short'], self::texts($split['overflow']));
    }

    public function testAQuietConversationIsClosedAndANewOneStarts(): void
    {
        $first = $this->service->open(7, 3, 'chat:1', '1');
        $this->assertSame($first->id, $this->service->open(7, 3, 'chat:1', '1')->id);

        $this->clock->advance(86401);
        $second = $this->service->open(7, 3, 'chat:1', '1');

        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue($this->repository->find($first->id)?->closed);
    }

    public function testTheAgentSeesTheSummaryThenRecentMessagesEndingWithTheNewOne(): void
    {
        $conversation = $this->service->open(7, 3, 'chat:1', '1');
        $this->repository->save($conversation->withSummary('They asked about pricing.', 0));
        $conversation = $this->repository->find($conversation->id);
        $this->assertNotNull($conversation);

        $this->service->recordPerson($conversation, 'Hello', 1, 'Ann');
        $this->service->recordAgent($conversation, 'Hi Ann', 9);
        $this->service->recordPerson($conversation, 'One more thing', 1, 'Ann');
        $last = $this->service->recordPerson($conversation, 'and another', 2, 'Bo');

        $run = new Run(5, 7, TriggerType::Message, ['conversation_id' => $conversation->id, 'message_id' => $last->id], 0);
        $transcript = $this->service->transcript($run);

        $this->assertNotNull($transcript);
        $this->assertSame([Message::ROLE_USER, Message::ROLE_ASSISTANT, Message::ROLE_USER], array_map(static fn (Message $m): string => $m->role, $transcript));
        $this->assertStringStartsWith("Summary of the earlier part of this conversation, for context:\nThey asked about pricing.", $transcript[0]->content);
        $this->assertStringEndsWith('Ann: Hello', $transcript[0]->content);
        $this->assertSame("Ann: One more thing\n\nBo: and another", $transcript[2]->content, 'Messages in a row from people are joined.');
    }

    public function testMessagesThatArriveLaterAreNotPartOfAnEarlierRun(): void
    {
        $conversation = $this->service->open(7, 3, 'chat:1', '1');
        $first = $this->service->recordPerson($conversation, 'first', 1, 'Ann');
        $this->service->recordPerson($conversation, 'second', 1, 'Ann');

        $transcript = $this->service->transcript(new Run(5, 7, TriggerType::Message, ['conversation_id' => $conversation->id, 'message_id' => $first->id], 0));

        $this->assertSame('Ann: first', $transcript[0]->content ?? '');
        $this->assertCount(1, $transcript ?? []);
    }

    public function testOlderMessagesAreFoldedIntoTheSummary(): void
    {
        $conversation = $this->service->open(7, 3, 'chat:1', '1');
        foreach (range(1, 6) as $i) {
            $this->service->recordPerson($conversation, 'message ' . $i, 1, 'Ann');
        }
        $this->model->replyWithText('Ann sent six messages.');

        $updated = $this->service->summarize($conversation, new Agent(7, 'Helper', 'x'));

        $this->assertSame('Ann sent six messages.', $updated->summary);
        $this->assertStringContainsString('Ann: message 2', $this->model->requests[0]->messages[0]->content);
        $this->assertStringNotContainsString('message 3', $this->model->requests[0]->messages[0]->content);
        $this->assertCount(4, $this->repository->messagesAfter($conversation->id, $updated->summarizedThrough));
    }

    public function testRunsThatAreNotMessagesHaveNoConversation(): void
    {
        $this->assertNull($this->service->transcript(new Run(5, 7, TriggerType::Manual, [], 0)));
    }
}
