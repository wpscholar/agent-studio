<?php

declare(strict_types=1);

namespace AgentStudio\Application\Conversations;

use AgentStudio\Contracts\Clock;
use AgentStudio\Contracts\ConversationRepository;
use AgentStudio\Contracts\Model\ModelException;
use AgentStudio\Contracts\Model\ModelRequest;
use AgentStudio\Contracts\ModelClient;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Conversation\ChatMessage;
use AgentStudio\Domain\Conversation\Conversation;
use AgentStudio\Domain\Conversation\ConversationWindow;
use AgentStudio\Domain\Conversation\Message;
use AgentStudio\Domain\Run\Run;

/**
 * Turns a conversation into what the agent sees, records its replies, and
 * keeps the running summary of older messages up to date.
 */
final class ConversationService
{
    private const SUMMARY_TOKENS = 600;

    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly ModelClient $model,
        private readonly Clock $clock,
        private readonly ConversationWindow $window = new ConversationWindow(),
    ) {
    }

    public function window(): ConversationWindow
    {
        return $this->window;
    }

    public function forRun(Run $run): ?Conversation
    {
        if ($run->triggerType() !== TriggerType::Message) {
            return null;
        }

        $id = $run->input()['conversation_id'] ?? null;

        return is_int($id) ? $this->conversations->find($id) : null;
    }

    /**
     * The opening of a message run's transcript: the summary of earlier
     * messages, then the recent ones word for word, ending with the new message.
     * Null when the run is not part of a conversation.
     *
     * @return list<Message>|null
     */
    public function transcript(Run $run): ?array
    {
        $conversation = $this->forRun($run);
        if ($conversation === null) {
            return null;
        }

        $messageId = $run->input()['message_id'] ?? 0;
        $recent = array_values(array_filter(
            $this->conversations->messagesAfter($conversation->id, $conversation->summarizedThrough),
            static fn (ChatMessage $m): bool => ! is_int($messageId) || $messageId <= 0 || $m->id <= $messageId
        ));
        $window = $this->window->split($recent)['window'];

        /** @var list<array{role: string, text: string}> $turns */
        $turns = [];
        $prefix = $conversation->summary !== ''
            ? "Summary of the earlier part of this conversation, for context:\n" . $conversation->summary . "\n\n---\n\n"
            : '';

        foreach ($window as $message) {
            $role = $message->isFromAgent() ? Message::ROLE_ASSISTANT : Message::ROLE_USER;
            $text = $message->isFromAgent() || $message->senderName === ''
                ? $message->text
                : $message->senderName . ': ' . $message->text;

            // Some models need turns to alternate, so consecutive messages from one side are joined.
            $last = count($turns) - 1;
            if ($last >= 0 && $turns[$last]['role'] === $role) {
                $turns[$last]['text'] .= "\n\n" . $text;
            } else {
                $turns[] = ['role' => $role, 'text' => $text];
            }
        }

        // The model must be answering a person, so the transcript starts and ends with one.
        while ($turns !== [] && $turns[0]['role'] === Message::ROLE_ASSISTANT) {
            array_shift($turns);
        }
        if ($turns === []) {
            $turns[] = ['role' => Message::ROLE_USER, 'text' => (string) ($run->input()['text'] ?? '')];
        }
        $turns[0]['text'] = $prefix . $turns[0]['text'];

        return array_map(
            static fn (array $turn): Message => $turn['role'] === Message::ROLE_USER ? Message::user($turn['text']) : Message::assistant($turn['text']),
            $turns
        );
    }

    public function recordPerson(Conversation $conversation, string $text, int $contactId, string $senderName): ChatMessage
    {
        $message = $this->conversations->addMessage(
            new ChatMessage(0, $conversation->id, ChatMessage::FROM_PERSON, $text, $contactId, 0, $this->clock->now(), $senderName)
        );
        $this->conversations->save($conversation->touched($this->clock->now()));

        return $message;
    }

    public function recordAgent(Conversation $conversation, string $text, int $runId): void
    {
        $this->conversations->addMessage(
            new ChatMessage(0, $conversation->id, ChatMessage::FROM_AGENT, $text, 0, $runId, $this->clock->now())
        );
        $this->conversations->save($conversation->touched($this->clock->now()));
    }

    /**
     * The open conversation in this chat or thread, or a new one when there is
     * none or the last one went quiet.
     */
    public function open(int $agentId, int $channelId, string $threadKey, string $address): Conversation
    {
        $now = $this->clock->now();
        $current = $this->conversations->findOpen($channelId, $threadKey);

        if ($current !== null && ! $this->window->isIdle($current, $now) && $current->agentId === $agentId) {
            return $current;
        }

        if ($current !== null) {
            $this->conversations->save($current->close());
        }

        return $this->conversations->save(new Conversation(0, $agentId, $channelId, $threadKey, $address, '', 0, false, $now, $now));
    }

    /**
     * Folds messages that no longer fit the window into the running summary.
     * Uses the agent's own model. If it fails, the old messages are simply
     * left out of view, which is safe; the next reply tries again.
     */
    public function summarize(Conversation $conversation, Agent $agent): Conversation
    {
        $overflow = $this->window->split($this->conversations->messagesAfter($conversation->id, $conversation->summarizedThrough))['overflow'];
        if ($overflow === []) {
            return $conversation;
        }

        $lines = [];
        foreach ($overflow as $message) {
            $who = $message->isFromAgent() ? 'Agent' : ($message->senderName !== '' ? $message->senderName : 'Person');
            $lines[] = $who . ': ' . $message->text;
        }

        $prompt = ($conversation->summary !== '' ? "Summary so far:\n" . $conversation->summary . "\n\n" : '')
            . "Messages to add:\n" . implode("\n", $lines);

        try {
            $response = $this->model->generate(new ModelRequest(
                'You keep a running summary of a chat between people and an assistant. Write an updated summary in under 200 words: '
                    . 'what was asked, what was done or decided, names and facts that matter, and anything still open. Plain sentences, no headings.',
                [Message::user($prompt)],
                [],
                $agent->model,
                self::SUMMARY_TOKENS,
                $agent->provider
            ));
        } catch (ModelException) {
            return $conversation;
        }

        $summary = trim($response->text);
        if ($summary === '') {
            return $conversation;
        }

        return $this->conversations->save($conversation->withSummary($summary, $overflow[count($overflow) - 1]->id));
    }
}
