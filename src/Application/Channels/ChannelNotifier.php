<?php

declare(strict_types=1);

namespace AgentStudio\Application\Channels;

use AgentStudio\Application\Approval\ApprovalTokens;
use AgentStudio\Application\Conversations\ConversationService;
use AgentStudio\Contracts\ApprovalPolicy;
use AgentStudio\Contracts\Channel\ChannelEndpoints;
use AgentStudio\Contracts\Channel\ChannelPhrases;
use AgentStudio\Contracts\Channel\MessageButton;
use AgentStudio\Contracts\Channel\OutgoingMessage;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\Notifier;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Run\PendingApproval;
use AgentStudio\Domain\Run\Run;

/**
 * Sends approval requests to every channel set up for notifications whose
 * owner may approve this agent, with Approve and Reject buttons.
 */
final class ChannelNotifier implements Notifier
{
    public function __construct(
        private readonly ChannelRepository $channels,
        private readonly ChannelService $service,
        private readonly ConversationService $conversations,
        private readonly ApprovalPolicy $policy,
        private readonly ApprovalTokens $tokens,
        private readonly ChannelEndpoints $endpoints,
        private readonly ChannelPhrases $phrases,
    ) {
    }

    public function approvalRequested(Agent $agent, Run $run, PendingApproval $pending): void
    {
        $conversation = $this->conversations->forRun($run);

        foreach ($this->channels->all() as $channel) {
            if (! $channel->isActive() || ! $channel->notify || $channel->ownerId <= 0) {
                continue;
            }

            $type = $this->service->type($channel);
            if (! $type->capabilities()->notify || ! $this->policy->canApprove($agent, $channel->ownerId)) {
                continue;
            }

            // The person was already asked in this very chat.
            if ($conversation !== null && $conversation->channelId === $channel->id && $conversation->address === $channel->notifyTo) {
                continue;
            }

            $buttons = [];
            foreach ([true, false] as $approve) {
                $buttons[] = new MessageButton(
                    $approve ? MessageButton::APPROVE : MessageButton::REJECT,
                    $approve ? $this->phrases->approveLabel() : $this->phrases->rejectLabel(),
                    $this->tokens->button($run, $approve),
                    $this->endpoints->approvalLink(
                        (int) $run->id(),
                        $approve,
                        $channel->id,
                        $this->tokens->link($run, $approve, $channel->id, $channel->ownerId)
                    )
                );
            }
            $buttons[] = new MessageButton(MessageButton::LINK, $this->phrases->viewRunLabel(), $this->endpoints->run((int) $run->id()));

            $this->service->send($channel, new OutgoingMessage(
                $channel->notifyTo,
                $this->phrases->approvalNeeded($agent->name, $pending->abilityName, $this->phrases->risk($pending->risk)),
                $this->phrases->approvalTitle($agent->name),
                $buttons,
                $this->endpoints->run((int) $run->id())
            ));
        }
    }
}
