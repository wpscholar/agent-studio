<?php

declare(strict_types=1);

namespace AgentStudio\Application\Channels;

use Throwable;
use AgentStudio\Application\Approval\ApprovalTokens;
use AgentStudio\Application\Conversations\ConversationService;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\ApprovalPolicy;
use AgentStudio\Contracts\Channel\ChannelPhrases;
use AgentStudio\Contracts\Channel\MessageButton;
use AgentStudio\Contracts\Channel\OutgoingMessage;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Contracts\RunObserver;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\RunStatus;

/**
 * Sends the agent's answer back to the chat it came from, and keeps the
 * conversation's summary up to date. If the agent stops for approval, says so
 * in the chat, with buttons when the person there may decide.
 */
final class ConversationReplies implements RunObserver
{
    private const MAX_ERROR = 300;

    public function __construct(
        private readonly ConversationService $conversations,
        private readonly ChannelService $channels,
        private readonly ChannelRepository $channelRepository,
        private readonly ContactRepository $contacts,
        private readonly AgentRepository $agents,
        private readonly ApprovalPolicy $policy,
        private readonly ApprovalTokens $tokens,
        private readonly ChannelPhrases $phrases,
    ) {
    }

    public function runStopped(Run $run): void
    {
        $conversation = $this->conversations->forRun($run);
        $channel = $conversation === null ? null : $this->channelRepository->find($conversation->channelId);
        $agent = $this->agents->find($run->agentId());
        if ($conversation === null || $channel === null || $agent === null) {
            return;
        }

        switch ($run->status()) {
            case RunStatus::Completed:
                $text = trim($run->output()) !== '' ? trim($run->output()) : $this->phrases->emptyReply();
                $this->channels->send($channel, new OutgoingMessage($conversation->address, $text));
                $this->conversations->recordAgent($conversation, $text, (int) $run->id());

                try {
                    $this->conversations->summarize($conversation, $agent);
                } catch (Throwable) {
                    // A summary can wait for the next reply.
                }
                break;

            case RunStatus::Failed:
                $this->channels->send($channel, new OutgoingMessage(
                    $conversation->address,
                    $this->phrases->failed(mb_substr($run->error(), 0, self::MAX_ERROR))
                ));
                break;

            case RunStatus::AwaitingApproval:
                $pending = $run->pendingApproval();
                if ($pending === null) {
                    break;
                }

                $contactId = $run->input()['contact_id'] ?? 0;
                $contact = is_int($contactId) ? $this->contacts->find($contactId) : null;
                $canDecide = $contact !== null && $contact->userId > 0
                    && $this->channels->type($channel)->capabilities()->approve
                    && $this->policy->canApprove($agent, $contact->userId);

                $this->channels->send($channel, new OutgoingMessage(
                    $conversation->address,
                    $this->phrases->waitingForApproval($pending->abilityName, $canDecide),
                    '',
                    $canDecide ? [
                        new MessageButton(MessageButton::APPROVE, $this->phrases->approveLabel(), $this->tokens->button($run, true)),
                        new MessageButton(MessageButton::REJECT, $this->phrases->rejectLabel(), $this->tokens->button($run, false)),
                    ] : []
                ));
                break;

            default:
                break;
        }
    }
}
