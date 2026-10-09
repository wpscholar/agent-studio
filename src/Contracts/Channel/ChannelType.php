<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

use AgentStudio\Domain\Channel\Channel;
use AgentStudio\Domain\Channel\ChannelCapabilities;
use AgentStudio\Domain\Setup\SetupStep;

/**
 * A kind of channel: Telegram, ntfy, Slack… Agent Studio ships some; plugins
 * add theirs on the agent_studio_register_channel_types action.
 *
 * Agent Studio handles everything else for every type: the Channels screen
 * and its step-by-step setup, secrets, who may talk to the agent, pairing
 * people with their WordPress accounts, conversations, approvals and replies.
 */
interface ChannelType
{
    /** Short unique key, like "telegram". */
    public function key(): string;

    /** Name people see, like "Telegram". */
    public function label(): string;

    /** One or two plain sentences: what it is and when to pick it. */
    public function description(): string;

    public function capabilities(): ChannelCapabilities;

    /**
     * The setup, as numbered steps of plain instructions and fields.
     *
     * @return list<SetupStep>
     */
    public function setupSteps(): array;

    /**
     * Checks the settings and connects to the service (for example registers a
     * webhook). Returns settings to keep, such as a bot's username.
     *
     * @return array<string, string>
     *
     * @throws ChannelProblem
     */
    public function activate(Channel $channel, ChannelContext $context): array;

    /**
     * Disconnects from the service, best effort.
     */
    public function deactivate(Channel $channel, ChannelContext $context): void;

    /**
     * Reads a request the service sent. Must verify it came from the service.
     */
    public function receive(Channel $channel, ChannelContext $context, InboundRequest $request): InboundResult;

    /**
     * @throws ChannelProblem
     */
    public function send(Channel $channel, ChannelContext $context, OutgoingMessage $message): void;

    /**
     * Tells the service a button press was handled (some services show a spinner until then).
     */
    public function acknowledge(Channel $channel, ChannelContext $context, IncomingAction $action, string $text): void;

    /**
     * How a person links their account: the text to send, or a link that sends it.
     * Return an empty array when the channel cannot receive messages.
     *
     * @return array{text?: string, link?: string}
     */
    public function pairingInstructions(Channel $channel, string $code): array;
}
