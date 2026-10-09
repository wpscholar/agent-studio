<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Channel;

use AgentStudio\Domain\Channel\Channel;

/**
 * For channel types connected by signing in to the service ("Connect to Slack")
 * rather than by pasting a token. Agent Studio handles the state, the return
 * address and storing what comes back.
 */
interface AuthorizesWithOAuth
{
    /**
     * Where to send the person to approve the connection.
     *
     * @throws ChannelProblem When setup is not far enough along yet.
     */
    public function authorizationUrl(Channel $channel, ChannelContext $context, string $state): string;

    /**
     * Trades what came back for credentials.
     *
     * @param array<string, string> $query The return request's query parameters.
     *
     * @return array{settings: array<string, string>, secrets: array<string, string>}
     *
     * @throws ChannelProblem
     */
    public function completeAuthorization(Channel $channel, ChannelContext $context, array $query): array;

    /**
     * Keys of the secrets completeAuthorization() stores, so they are loaded and removed like setup secrets.
     *
     * @return list<string>
     */
    public function authorizationSecrets(): array;
}
