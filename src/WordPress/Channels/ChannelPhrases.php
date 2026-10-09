<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Channels;

use AgentStudio\Contracts\Channel\ChannelPhrases as Phrases;
use AgentStudio\Domain\Ability\Risk;
use AgentStudio\WordPress\Labels;

/**
 * What Agent Studio says in chats, translated.
 */
final class ChannelPhrases implements Phrases
{
    public function waitForAccess(string $name, string $site): string
    {
        return sprintf(
            /* translators: 1: person's name, 2: site name */
            __('Hi %1$s! Before you can talk to this agent, someone who manages %2$s needs to let you in. They can see your request now.', 'agent-studio'),
            $name !== '' ? $name : __('there', 'agent-studio'),
            $site
        );
    }

    public function notificationsOnly(): string
    {
        return __('This channel only sends notifications. Nobody reads messages here.', 'agent-studio');
    }

    public function agentOff(): string
    {
        return __('This agent is switched off right now, so it cannot answer.', 'agent-studio');
    }

    public function rateLimited(): string
    {
        return __('This agent has answered as many messages as it may this hour. Try again later.', 'agent-studio');
    }

    public function pairingFailed(): string
    {
        return __('That code did not work. Codes last 30 minutes and work once; get a new one from the Channels screen.', 'agent-studio');
    }

    public function paired(string $site, bool $hasAgent): string
    {
        return sprintf(
            /* translators: %s: site name */
            __('Done. This chat is linked to your account on %s.', 'agent-studio'),
            $site
        ) . ' ' . ($hasAgent ? __('Say hello to your agent any time.', 'agent-studio') : __('Notifications will arrive here.', 'agent-studio'));
    }

    public function buttonExpired(): string
    {
        return __('That button no longer works.', 'agent-studio');
    }

    public function alreadyDecided(): string
    {
        return __('This was already decided.', 'agent-studio');
    }

    public function notAllowedToApprove(): string
    {
        return __('Only people who may approve this agent’s actions can decide. Link your account first.', 'agent-studio');
    }

    public function decided(bool $approved): string
    {
        return $approved ? __('Approved. The agent will carry on.', 'agent-studio') : __('Rejected. The agent will carry on without it.', 'agent-studio');
    }

    public function approvalNeeded(string $agentName, string $ability, string $risk): string
    {
        return sprintf(
            /* translators: 1: agent name, 2: ability name, 3: risk label */
            __('“%1$s” wants to use “%2$s” (%3$s). Nothing happens until someone approves.', 'agent-studio'),
            $agentName,
            $ability,
            $risk
        );
    }

    public function approvalTitle(string $agentName): string
    {
        return sprintf(
            /* translators: %s: agent name */
            __('%s needs your approval', 'agent-studio'),
            $agentName
        );
    }

    public function waitingForApproval(string $ability, bool $canDecideHere): string
    {
        return sprintf(
            /* translators: %s: ability name */
            __('Before I go on I need approval to use “%s”.', 'agent-studio'),
            $ability
        ) . ' ' . ($canDecideHere ? __('You can decide here.', 'agent-studio') : __('I have asked the people who can approve it.', 'agent-studio'));
    }

    public function approveLabel(): string
    {
        return __('Approve', 'agent-studio');
    }

    public function rejectLabel(): string
    {
        return __('Reject', 'agent-studio');
    }

    public function viewRunLabel(): string
    {
        return __('Open in WordPress', 'agent-studio');
    }

    public function failed(string $reason): string
    {
        return sprintf(
            /* translators: %s: what went wrong */
            __('Sorry, I could not finish that: %s', 'agent-studio'),
            $reason
        );
    }

    public function emptyReply(): string
    {
        return __('Done.', 'agent-studio');
    }

    public function testMessage(string $site): string
    {
        return sprintf(
            /* translators: %s: site name */
            __('Test from Agent Studio on %s. If you can read this, notifications work.', 'agent-studio'),
            $site
        );
    }

    public function risk(Risk $risk): string
    {
        return Labels::risk($risk);
    }
}
