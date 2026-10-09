<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

use AgentStudio\Domain\Ability\Risk;

/**
 * What Agent Studio itself says in chats, so it can be translated.
 */
interface ChannelPhrases
{
    public function waitForAccess(string $name, string $site): string;

    public function notificationsOnly(): string;

    public function agentOff(): string;

    public function rateLimited(): string;

    public function pairingFailed(): string;

    public function paired(string $site, bool $hasAgent): string;

    public function buttonExpired(): string;

    public function alreadyDecided(): string;

    public function notAllowedToApprove(): string;

    public function decided(bool $approved): string;

    public function approvalNeeded(string $agentName, string $ability, string $risk): string;

    public function approvalTitle(string $agentName): string;

    public function waitingForApproval(string $ability, bool $canDecideHere): string;

    public function approveLabel(): string;

    public function rejectLabel(): string;

    public function viewRunLabel(): string;

    public function failed(string $reason): string;

    public function emptyReply(): string;

    public function testMessage(string $site): string;

    public function risk(Risk $risk): string;
}
