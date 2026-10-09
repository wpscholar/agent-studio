<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Support;

use AgentStudio\Contracts\Channel\ChannelPhrases;
use AgentStudio\Domain\Ability\Risk;

final class EnglishChannelPhrases implements ChannelPhrases
{
    public function waitForAccess(string $name, string $site): string
    {
        return "Hi $name! Someone at $site needs to let you in.";
    }

    public function notificationsOnly(): string
    {
        return 'Notifications only.';
    }

    public function agentOff(): string
    {
        return 'Agent is off.';
    }

    public function rateLimited(): string
    {
        return 'Rate limited.';
    }

    public function pairingFailed(): string
    {
        return 'That code did not work.';
    }

    public function paired(string $site, bool $hasAgent): string
    {
        return "Linked to $site.";
    }

    public function buttonExpired(): string
    {
        return 'Expired.';
    }

    public function alreadyDecided(): string
    {
        return 'Already decided.';
    }

    public function notAllowedToApprove(): string
    {
        return 'Not allowed.';
    }

    public function decided(bool $approved): string
    {
        return $approved ? 'Approved.' : 'Rejected.';
    }

    public function approvalNeeded(string $agentName, string $ability, string $risk): string
    {
        return "$agentName wants $ability ($risk).";
    }

    public function approvalTitle(string $agentName): string
    {
        return "$agentName needs approval";
    }

    public function waitingForApproval(string $ability, bool $canDecideHere): string
    {
        return "Waiting for approval of $ability" . ($canDecideHere ? ' (decide here)' : '') . '.';
    }

    public function approveLabel(): string
    {
        return 'Approve';
    }

    public function rejectLabel(): string
    {
        return 'Reject';
    }

    public function viewRunLabel(): string
    {
        return 'Open';
    }

    public function failed(string $reason): string
    {
        return "Failed: $reason";
    }

    public function emptyReply(): string
    {
        return 'Done.';
    }

    public function testMessage(string $site): string
    {
        return "Test from $site.";
    }

    public function risk(Risk $risk): string
    {
        return $risk->value;
    }
}
