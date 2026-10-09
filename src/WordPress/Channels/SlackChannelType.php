<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Channels;

use AgentStudio\Contracts\Channel\AuthorizesWithOAuth;
use AgentStudio\Contracts\Channel\ChannelContext;
use AgentStudio\Contracts\Channel\ChannelProblem;
use AgentStudio\Contracts\Channel\ChannelType;
use AgentStudio\Contracts\Channel\GuidedSetup;
use AgentStudio\Contracts\Channel\InboundRequest;
use AgentStudio\Contracts\Channel\InboundResult;
use AgentStudio\Contracts\Channel\IncomingAction;
use AgentStudio\Contracts\Channel\IncomingMessage;
use AgentStudio\Contracts\Channel\MessageButton;
use AgentStudio\Contracts\Channel\OutgoingMessage;
use AgentStudio\Contracts\Http\HttpClient;
use AgentStudio\Contracts\Http\HttpException;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Domain\Channel\Channel;
use AgentStudio\Domain\Channel\ChannelCapabilities;
use AgentStudio\Domain\Setup\SetupField;
use AgentStudio\Domain\Setup\SetupStep;

/**
 * Slack: people DM the agent or @mention it in a channel (it answers in the
 * thread), and approval requests arrive with Approve and Reject buttons.
 *
 * Each site makes its own Slack app from a manifest Agent Studio fills in with
 * this channel's addresses, then connects it with "Connect to Slack" (OAuth).
 * Every request from Slack is checked against the app's signing secret.
 */
final class SlackChannelType implements ChannelType, GuidedSetup, AuthorizesWithOAuth
{
    public const KEY = 'slack';
    private const API = 'https://slack.com/api/';
    private const SCOPES = ['app_mentions:read', 'chat:write', 'im:history', 'users:read'];
    private const MAX_CHARS = 3900;
    private const MAX_AGE = 300;

    public function __construct(private readonly HttpClient $http)
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Slack';
    }

    public function description(): string
    {
        return __('Chat with an agent in Slack: send it a direct message, or @mention it in a channel and it answers in the thread. Approve its actions with a click. You make a Slack app for your workspace in a few clicks.', 'agent-studio');
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(receive: true, reply: true, notify: true, approve: true);
    }

    public function setupSteps(): array
    {
        return [
            new SetupStep(
                __('What happens next', 'agent-studio'),
                [
                    __('After you save, this channel’s page walks you through three short steps: create the Slack app (the settings are filled in for you), copy three values from Slack, and click Connect to Slack.', 'agent-studio'),
                    __('You need permission to add apps to your Slack workspace. Your site must be reachable from the internet over https.', 'agent-studio'),
                ]
            ),
        ];
    }

    public function followUpSteps(Channel $channel, string $inboundUrl, string $redirectUrl): array
    {
        $connected = $channel->setting('team_name') !== '';

        return [
            new SetupStep(
                __('Create the Slack app', 'agent-studio'),
                [
                    __('This opens Slack with your app’s settings already filled in. Choose your workspace, check the summary, and click Create.', 'agent-studio'),
                    __('Slack may say the request URL is not verified yet. That is fine: it is verified once you finish the next step.', 'agent-studio'),
                ],
                [],
                [['label' => __('Create the app in Slack', 'agent-studio'), 'url' => 'https://api.slack.com/apps?new_app=1&manifest_json=' . rawurlencode((string) wp_json_encode($this->manifest($channel, $inboundUrl, $redirectUrl)))]],
                '',
                '',
                $connected
            ),
            new SetupStep(
                __('Copy three values from Slack', 'agent-studio'),
                [
                    __('In your new app’s page in Slack, open Basic Information. Under App Credentials, copy the Client ID, the Client Secret and the Signing Secret into the boxes below, then save.', 'agent-studio'),
                ],
                [
                    new SetupField('client_id', __('Client ID', 'agent-studio'), SetupField::TEXT, '', true, '1234567890.1234567890'),
                    new SetupField('client_secret', __('Client Secret', 'agent-studio'), SetupField::SECRET, __('Kept encrypted.', 'agent-studio'), true),
                    new SetupField('signing_secret', __('Signing Secret', 'agent-studio'), SetupField::SECRET, __('Kept encrypted. Agent Studio uses it to check that messages really come from Slack.', 'agent-studio'), true),
                ],
                [],
                '',
                '',
                $connected
            ),
            new SetupStep(
                __('Connect to Slack', 'agent-studio'),
                [
                    __('Slack asks you to allow the app in your workspace, then sends you back here and the channel switches on.', 'agent-studio'),
                    __('Then, in Slack, find the app under Apps and send it a message, or invite it to a channel and @mention it.', 'agent-studio'),
                ],
                [],
                [],
                SetupStep::ACTION_AUTHORIZE,
                $connected ? __('Connect again', 'agent-studio') : __('Connect to Slack', 'agent-studio'),
                $connected && $channel->isActive()
            ),
        ];
    }

    public function authorizationUrl(Channel $channel, ChannelContext $context, string $state): string
    {
        if ($channel->setting('client_id') === '' || $context->secret('client_secret') === '' || $context->secret('signing_secret') === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('First copy the Client ID, Client Secret and Signing Secret from Slack, and save.', 'agent-studio'));
        }

        return 'https://slack.com/oauth/v2/authorize?' . http_build_query([
            'client_id' => $channel->setting('client_id'),
            'scope' => implode(',', self::SCOPES),
            'redirect_uri' => $context->redirectUrl,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function completeAuthorization(Channel $channel, ChannelContext $context, array $query): array
    {
        $code = $query['code'] ?? '';
        if ($code === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('Slack did not finish connecting. Try again.', 'agent-studio'));
        }

        $data = $this->api('oauth.v2.access', [
            'client_id' => $channel->setting('client_id'),
            'client_secret' => $context->secret('client_secret'),
            'code' => $code,
            'redirect_uri' => $context->redirectUrl,
        ], '', true);

        $token = is_string($data['access_token'] ?? null) ? $data['access_token'] : '';
        if (! str_starts_with($token, 'xoxb-')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('Slack did not send a bot token. Check the app has a bot user, then connect again.', 'agent-studio'));
        }

        $team = is_array($data['team'] ?? null) ? $data['team'] : [];

        return [
            'secrets' => ['bot_token' => $token],
            'settings' => [
                'team_id' => is_string($team['id'] ?? null) ? $team['id'] : '',
                'team_name' => is_string($team['name'] ?? null) ? $team['name'] : '',
                'app_id' => is_string($data['app_id'] ?? null) ? $data['app_id'] : '',
                'bot_user_id' => is_string($data['bot_user_id'] ?? null) ? $data['bot_user_id'] : '',
            ],
        ];
    }

    public function authorizationSecrets(): array
    {
        return ['bot_token'];
    }

    public function activate(Channel $channel, ChannelContext $context): array
    {
        if ($context->secret('bot_token') === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('Finish the steps on this page, then click Connect to Slack.', 'agent-studio'));
        }
        if ($context->secret('signing_secret') === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('Copy the Signing Secret from Slack and save it, so Agent Studio can check messages really come from Slack.', 'agent-studio'));
        }

        $auth = $this->api('auth.test', [], $context->secret('bot_token'));

        return [
            'bot_user_id' => is_string($auth['user_id'] ?? null) ? $auth['user_id'] : $channel->setting('bot_user_id'),
            'team_name' => is_string($auth['team'] ?? null) ? $auth['team'] : $channel->setting('team_name'),
        ];
    }

    public function deactivate(Channel $channel, ChannelContext $context): void
    {
        // Switching off just stops answering; the app stays installed so switching on again needs no new sign-in.
    }

    public function receive(Channel $channel, ChannelContext $context, InboundRequest $request): InboundResult
    {
        $secret = $context->secret('signing_secret');

        // While the app is being created the signing secret is not saved yet. Echoing Slack's
        // check challenge reveals nothing and starts nothing, so it is answered regardless.
        $data = $request->json();
        if (is_array($data) && ($data['type'] ?? '') === 'url_verification' && ($secret === '' || $this->verified($request, $secret))) {
            return new InboundResult(200, (string) wp_json_encode(['challenge' => (string) ($data['challenge'] ?? '')]), 'application/json');
        }

        if ($secret === '' || ! $this->verified($request, $secret)) {
            return InboundResult::rejected();
        }

        // Button presses arrive form-encoded with a JSON "payload".
        if ($data === null) {
            parse_str($request->body, $form);
            $payload = is_string($form['payload'] ?? null) ? json_decode($form['payload'], true) : null;

            return InboundResult::accepted(is_array($payload) ? $this->actions($payload) : []);
        }

        $event = is_array($data['event'] ?? null) ? $data['event'] : [];
        if (($data['type'] ?? '') !== 'event_callback' || $event === []) {
            return InboundResult::accepted();
        }

        $message = $this->message($channel, $context, $event, is_string($data['event_id'] ?? null) ? $data['event_id'] : '');

        return InboundResult::accepted($message === null ? [] : [$message]);
    }

    public function send(Channel $channel, ChannelContext $context, OutgoingMessage $message): void
    {
        if ($message->to === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('Link your Slack account first (send the app your code), so Agent Studio knows where to send notifications.', 'agent-studio'));
        }

        [$slackChannel, $threadTs] = array_pad(explode('|', $message->to, 2), 2, '');
        $text = ($message->title !== '' ? '*' . $message->title . "*\n" : '') . $message->text;
        $chunks = $text === '' ? [''] : mb_str_split($text, self::MAX_CHARS);

        $elements = [];
        foreach ($message->buttons as $i => $button) {
            if ($button->kind === MessageButton::LINK) {
                $elements[] = ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => $button->label], 'url' => $button->value, 'action_id' => 'agent_studio_link_' . $i];
            } else {
                $elements[] = [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => $button->label],
                    'value' => $button->value,
                    'action_id' => 'agent_studio_' . $button->kind,
                    'style' => $button->kind === MessageButton::APPROVE ? 'primary' : 'danger',
                ];
            }
        }

        foreach ($chunks as $i => $chunk) {
            $params = ['channel' => $slackChannel, 'text' => $chunk, 'unfurl_links' => false];
            if ($threadTs !== '') {
                $params['thread_ts'] = $threadTs;
            }
            if ($i === count($chunks) - 1 && $elements !== []) {
                $params['blocks'] = [
                    ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $chunk]],
                    ['type' => 'actions', 'elements' => $elements],
                ];
            }

            $this->api('chat.postMessage', $params, $context->secret('bot_token'));
        }
    }

    public function acknowledge(Channel $channel, ChannelContext $context, IncomingAction $action, string $text): void
    {
        if (! str_starts_with($action->reference, 'https://hooks.slack.com/')) {
            return;
        }

        try {
            // Replace the buttons with what happened, so nobody presses them twice.
            $this->http->send(HttpRequest::json($action->reference, ['replace_original' => true, 'text' => $text], [], 10));
        } catch (HttpException) {
            // The decision is made either way.
        }
    }

    public function pairingInstructions(Channel $channel, string $code): array
    {
        $app = $channel->setting('app_id');
        $team = $channel->setting('team_id');

        return [
            'text' => $code,
            'link' => $app !== '' ? 'https://slack.com/app_redirect?app=' . rawurlencode($app) . ($team !== '' ? '&team=' . rawurlencode($team) : '') : '',
        ];
    }

    /**
     * Slack's request signing: HMAC-SHA256 of "v0:timestamp:body", within five minutes.
     */
    private function verified(InboundRequest $request, string $secret): bool
    {
        $timestamp = $request->header('x-slack-request-timestamp');
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_AGE) {
            return false;
        }

        $expected = 'v0=' . hash_hmac('sha256', 'v0:' . $timestamp . ':' . $request->body, $secret);

        return hash_equals($expected, $request->header('x-slack-signature'));
    }

    /**
     * @param array<string, mixed> $event
     */
    private function message(Channel $channel, ChannelContext $context, array $event, string $eventId): ?IncomingMessage
    {
        $type = $event['type'] ?? '';
        $isDm = $type === 'message' && ($event['channel_type'] ?? '') === 'im';
        if (! $isDm && $type !== 'app_mention') {
            return null;
        }

        // Ignore the bot itself, other bots, edits, joins and the like.
        $user = is_string($event['user'] ?? null) ? $event['user'] : '';
        if ($user === '' || isset($event['bot_id']) || isset($event['subtype']) || $user === $channel->setting('bot_user_id')) {
            return null;
        }

        $slackChannel = is_string($event['channel'] ?? null) ? $event['channel'] : '';
        $ts = is_string($event['ts'] ?? null) ? $event['ts'] : '';
        $threadTs = is_string($event['thread_ts'] ?? null) ? $event['thread_ts'] : $ts;
        $text = is_string($event['text'] ?? null) ? $event['text'] : '';
        $text = trim((string) preg_replace('/<@[A-Z0-9]+>/', '', $text));
        if ($slackChannel === '' || $text === '') {
            return null;
        }

        return new IncomingMessage(
            $eventId !== '' ? $eventId : $slackChannel . ':' . $ts,
            $user,
            $this->userName($context, $user),
            $text,
            $isDm ? 'im:' . $slackChannel : 'thread:' . $slackChannel . ':' . $threadTs,
            $isDm ? $slackChannel : $slackChannel . '|' . $threadTs,
            $isDm ? $slackChannel : ''
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<IncomingAction>
     */
    private function actions(array $payload): array
    {
        if (($payload['type'] ?? '') !== 'block_actions') {
            return [];
        }

        $user = is_array($payload['user'] ?? null) ? $payload['user'] : [];
        $actions = [];
        foreach (is_array($payload['actions'] ?? null) ? $payload['actions'] : [] as $action) {
            $value = is_array($action) && is_string($action['value'] ?? null) ? $action['value'] : '';
            if ($value === '') {
                continue;
            }

            $actions[] = new IncomingAction(
                is_string($user['id'] ?? null) ? $user['id'] : '',
                is_string($user['name'] ?? null) ? $user['name'] : '',
                $value,
                is_string($payload['channel']['id'] ?? null) ? $payload['channel']['id'] : '',
                is_string($payload['response_url'] ?? null) ? $payload['response_url'] : ''
            );
        }

        return $actions;
    }

    private function userName(ChannelContext $context, string $userId): string
    {
        try {
            $info = $this->api('users.info', ['user' => $userId], $context->secret('bot_token'), true);
        } catch (ChannelProblem) {
            return $userId;
        }

        $user = is_array($info['user'] ?? null) ? $info['user'] : [];
        $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
        foreach ([$profile['display_name'] ?? null, $profile['real_name'] ?? null, $user['name'] ?? null] as $name) {
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $userId;
    }

    /**
     * The app manifest Slack's "create app" page is filled in with.
     *
     * @return array<string, mixed>
     */
    private function manifest(Channel $channel, string $inboundUrl, string $redirectUrl): array
    {
        $name = mb_substr($channel->name, 0, 35);
        $handle = substr(trim((string) preg_replace('/[^a-z0-9._-]+/', '-', strtolower($channel->name)), '-'), 0, 80);

        return [
            'display_information' => ['name' => $name !== '' ? $name : 'Agent Studio', 'description' => 'An AI agent from your WordPress site.'],
            'features' => [
                'bot_user' => ['display_name' => $handle !== '' ? $handle : 'agent', 'always_online' => true],
                'app_home' => ['home_tab_enabled' => false, 'messages_tab_enabled' => true, 'messages_tab_read_only_enabled' => false],
            ],
            'oauth_config' => ['redirect_urls' => [$redirectUrl], 'scopes' => ['bot' => self::SCOPES]],
            'settings' => [
                'event_subscriptions' => ['request_url' => $inboundUrl, 'bot_events' => ['app_mention', 'message.im']],
                'interactivity' => ['is_enabled' => true, 'request_url' => $inboundUrl],
                'org_deploy_enabled' => false,
                'socket_mode_enabled' => false,
                'token_rotation_enabled' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function api(string $method, array $params, string $token, bool $form = false): array
    {
        $headers = $token !== '' ? ['Authorization' => 'Bearer ' . $token] : [];
        $fields = array_map(static fn ($value): string => is_scalar($value) ? (string) $value : (string) wp_json_encode($value), $params);

        try {
            $response = $this->http->send(
                $form || $token === ''
                    ? HttpRequest::form(self::API . $method, $fields, $headers)
                    : HttpRequest::json(self::API . $method, $params, $headers + ['Accept' => 'application/json'], 20)
            );
        } catch (HttpException $e) {
            $problem = sprintf(
                /* translators: %s: error message */
                __('Slack could not be reached: %s', 'agent-studio'),
                $e->getMessage()
            );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem($problem);
        }

        $data = $response->json() ?? [];
        if (($data['ok'] ?? false) !== true) {
            $error = is_string($data['error'] ?? null) ? $data['error'] : sprintf('HTTP %d', $response->status);
            $problem = match ($error) {
                'invalid_auth', 'not_authed', 'token_revoked', 'account_inactive' => __('Slack no longer accepts this app’s token. Click Connect to Slack again.', 'agent-studio'),
                'not_in_channel', 'channel_not_found' => __('The app is not in that Slack channel. Invite it with /invite, then try again.', 'agent-studio'),
                'invalid_client_id', 'bad_client_secret' => __('Slack did not accept the Client ID or Client Secret. Copy them again from Basic Information.', 'agent-studio'),
                default => sprintf(
                    /* translators: %s: error code from Slack */
                    __('Slack said: %s', 'agent-studio'),
                    $error
                ),
            };
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem($problem);
        }

        return $data;
    }
}
