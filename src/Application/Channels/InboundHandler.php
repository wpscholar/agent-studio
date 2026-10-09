<?php

declare(strict_types=1);

namespace AgentStudio\Application\Channels;

use AgentStudio\Application\Approval\ApprovalTokens;
use AgentStudio\Application\Approval\ResolveApprovalHandler;
use AgentStudio\Application\Conversations\ConversationService;
use AgentStudio\Application\Dispatch\DispatchOutcome;
use AgentStudio\Application\Dispatch\DispatchTriggerHandler;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\ApprovalPolicy;
use AgentStudio\Contracts\Channel\ChannelPhrases;
use AgentStudio\Contracts\Channel\InboundRequest;
use AgentStudio\Contracts\Channel\InboundResult;
use AgentStudio\Contracts\Channel\IncomingAction;
use AgentStudio\Contracts\Channel\IncomingMessage;
use AgentStudio\Contracts\Channel\OutgoingMessage;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\Clock;
use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Channel\Channel;
use AgentStudio\Domain\Channel\Contact;
use AgentStudio\Domain\Channel\ContactStatus;
use AgentStudio\Domain\Run\RunStatus;

/**
 * Handles what arrives from a channel: messages from people and presses of
 * Approve and Reject buttons.
 *
 * Only people who were let in can talk to the agent. Someone new is asked to
 * wait once, and shows up on the channel's screen for someone to allow. People
 * link their chat account to their WordPress account by sending a pairing code;
 * only linked people can approve actions from the chat, and only if their
 * WordPress account may approve them.
 */
final class InboundHandler
{
    private const DEDUPE_WINDOW = 86400;
    private const MAX_TEXT = 8000;

    public function __construct(
        private readonly ChannelService $channels,
        private readonly ChannelRepository $channelRepository,
        private readonly ContactRepository $contacts,
        private readonly ConversationService $conversations,
        private readonly AgentRepository $agents,
        private readonly DispatchTriggerHandler $dispatch,
        private readonly RunRepository $runs,
        private readonly ResolveApprovalHandler $approvals,
        private readonly ApprovalTokens $tokens,
        private readonly ApprovalPolicy $policy,
        private readonly ChannelPhrases $phrases,
        private readonly Clock $clock,
    ) {
    }

    public function handle(Channel $channel, InboundRequest $request): InboundResult
    {
        $type = $this->channels->type($channel);
        $result = $type->receive($channel, $this->channels->context($channel), $request);

        if (! $channel->isActive()) {
            return $result;
        }

        foreach ($result->events as $event) {
            if ($event instanceof IncomingMessage) {
                $this->message($channel, $event);
            } else {
                $this->action($channel, $event);
            }
        }

        return $result;
    }

    private function message(Channel $channel, IncomingMessage $message): void
    {
        $now = $this->clock->now();
        $existing = $this->contacts->findByExternalId($channel->id, $message->senderId);
        $contact = ($existing ?? new Contact(0, $channel->id, $message->senderId, $message->senderName, ContactStatus::Pending, 0, '', $now))
            ->seen($message->senderName, $message->directAddress, $now);
        $contact = $this->contacts->save($contact);

        if ($this->pair($channel, $contact, $message)) {
            return;
        }

        if ($contact->status === ContactStatus::Blocked) {
            return;
        }

        if (! $contact->isAllowed()) {
            if ($existing === null) {
                $this->reply($channel, $message->replyAddress, $this->phrases->waitForAccess($message->senderName, $this->channels->context($channel)->siteName));
            }

            return;
        }

        $agent = $channel->agentId > 0 ? $this->agents->find($channel->agentId) : null;
        if ($agent === null) {
            $this->reply($channel, $message->replyAddress, $this->phrases->notificationsOnly());

            return;
        }

        $text = mb_substr(trim($message->text), 0, self::MAX_TEXT);
        if ($text === '') {
            return;
        }

        $conversation = $this->conversations->open($agent->id, $channel->id, $message->threadKey, $message->replyAddress);
        $chat = $this->conversations->recordPerson($conversation, $text, $contact->id, $contact->name);

        $result = $this->dispatch->handle(TriggerEvent::targeted(
            TriggerType::Message,
            $agent->id,
            [
                'conversation_id' => $conversation->id,
                'message_id' => $chat->id,
                'channel' => $channel->name,
                'contact_id' => $contact->id,
                'from' => $contact->name,
                'text' => $text,
            ],
            'message:' . $channel->id . ':' . $message->messageId,
            self::DEDUPE_WINDOW
        ));

        $outcome = $result->forAgent($agent->id)?->outcome;
        if ($outcome === DispatchOutcome::Skipped) {
            $this->reply($channel, $message->replyAddress, $this->phrases->agentOff());
        } elseif ($outcome === DispatchOutcome::RateLimited) {
            $this->reply($channel, $message->replyAddress, $this->phrases->rateLimited());
        }
    }

    /**
     * A pairing code links the chat account to the WordPress account that asked for it.
     */
    private function pair(Channel $channel, Contact $contact, IncomingMessage $message): bool
    {
        if (preg_match('/^\/?(?:start|link|pair)?\s*(\d{6})\s*$/i', trim($message->text), $m) !== 1) {
            return false;
        }

        $userId = $this->channels->takePairing($channel, $m[1]);
        if ($userId === null || ! $this->policy->canChat($userId)) {
            $this->reply($channel, $message->replyAddress, $this->phrases->pairingFailed());

            return true;
        }

        $contact = $this->contacts->save($contact->linkedTo($userId));

        // The person who set the channel up gets its notifications in this chat.
        if ($userId === $channel->ownerId && $contact->address !== '') {
            $this->channelRepository->save($channel->with(['notifyTo' => $contact->address]));
        }

        $this->reply($channel, $message->replyAddress, $this->phrases->paired($this->channels->context($channel)->siteName, $channel->agentId > 0));

        return true;
    }

    private function action(Channel $channel, IncomingAction $action): void
    {
        $type = $this->channels->type($channel);
        $context = $this->channels->context($channel);

        $parsed = ApprovalTokens::parseButton($action->value);
        $run = $parsed === null ? null : $this->runs->find($parsed['run']);
        if ($parsed === null || $run === null) {
            $type->acknowledge($channel, $context, $action, $this->phrases->buttonExpired());

            return;
        }

        if ($run->status() !== RunStatus::AwaitingApproval || ! $this->tokens->verifyButton($run, $parsed['approve'], $parsed['signature'])) {
            $type->acknowledge($channel, $context, $action, $this->phrases->alreadyDecided());

            return;
        }

        $contact = $this->contacts->findByExternalId($channel->id, $action->senderId);
        $agent = $this->agents->find($run->agentId());
        if ($contact === null || $contact->userId <= 0 || $agent === null || ! $this->policy->canApprove($agent, $contact->userId)) {
            $type->acknowledge($channel, $context, $action, $this->phrases->notAllowedToApprove());

            return;
        }

        $done = $this->approvals->handle((int) $run->id(), $parsed['approve'], $contact->userId);
        $type->acknowledge(
            $channel,
            $context,
            $action,
            $done ? $this->phrases->decided($parsed['approve']) : $this->phrases->alreadyDecided()
        );
    }

    private function reply(Channel $channel, string $address, string $text): void
    {
        $this->channels->send($channel, new OutgoingMessage($address, $text));
    }
}
