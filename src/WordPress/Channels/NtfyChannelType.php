<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Channels;

use AgentStudio\Application\OAuth\OAuthDiscovery;
use AgentStudio\Contracts\Channel\ChannelContext;
use AgentStudio\Contracts\Channel\ChannelProblem;
use AgentStudio\Contracts\Channel\ChannelType;
use AgentStudio\Contracts\Channel\InboundRequest;
use AgentStudio\Contracts\Channel\InboundResult;
use AgentStudio\Contracts\Channel\IncomingAction;
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
 * ntfy (ntfy.sh, or your own server): push notifications to a phone or desktop,
 * with Approve and Reject buttons. One-way: people cannot chat through it.
 */
final class NtfyChannelType implements ChannelType
{
    public const KEY = 'ntfy';
    private const DEFAULT_SERVER = 'https://ntfy.sh';
    private const MAX_BYTES = 4000;

    public function __construct(private readonly HttpClient $http)
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'ntfy';
    }

    public function description(): string
    {
        return __('Free push notifications on your phone or computer. Get approval requests with Approve and Reject buttons, wherever you are. Notifications only: you cannot chat with an agent here.', 'agent-studio');
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(receive: false, reply: false, notify: true, approve: true);
    }

    public function setupSteps(): array
    {
        return [
            new SetupStep(
                __('Get the ntfy app', 'agent-studio'),
                [
                    __('Install ntfy on your phone (App Store or Google Play) or open the web app at https://ntfy.sh/app. It is free and needs no account.', 'agent-studio'),
                ]
            ),
            new SetupStep(
                __('Choose a topic', 'agent-studio'),
                [
                    __('A topic is like a private channel name. On the public ntfy.sh server anyone who knows the name can read it, so we picked a hard-to-guess one for you.', 'agent-studio'),
                ],
                [
                    new SetupField(
                        'topic',
                        __('Topic', 'agent-studio'),
                        SetupField::TEXT,
                        __('Letters, numbers, hyphens and underscores, up to 64.', 'agent-studio'),
                        true,
                        '',
                        [],
                        'agent-studio-' . strtolower(wp_generate_password(14, false))
                    ),
                    new SetupField(
                        'server',
                        __('Server', 'agent-studio'),
                        SetupField::URL,
                        __('Leave this as it is unless you run your own ntfy server.', 'agent-studio'),
                        true,
                        self::DEFAULT_SERVER,
                        [],
                        self::DEFAULT_SERVER
                    ),
                    new SetupField(
                        'token',
                        __('Access token', 'agent-studio'),
                        SetupField::SECRET,
                        __('Only if your topic is protected. Create a token in ntfy under Account → Access tokens; it starts with tk_.', 'agent-studio')
                    ),
                ]
            ),
            new SetupStep(
                __('Subscribe in the app', 'agent-studio'),
                [
                    __('In the ntfy app, tap + (Subscribe to topic) and enter the topic name above. If you use your own server, turn on “Use another server” and enter its address.', 'agent-studio'),
                    __('Then save here and send a test notification to check it arrives.', 'agent-studio'),
                ]
            ),
        ];
    }

    public function activate(Channel $channel, ChannelContext $context): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $channel->setting('topic')) !== 1) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('The topic can only use letters, numbers, hyphens and underscores, up to 64.', 'agent-studio'));
        }
        if (! OAuthDiscovery::isHttpUrl(self::server($channel))) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('The server address must start with https://.', 'agent-studio'));
        }

        return [];
    }

    public function deactivate(Channel $channel, ChannelContext $context): void
    {
    }

    public function receive(Channel $channel, ChannelContext $context, InboundRequest $request): InboundResult
    {
        return InboundResult::accepted();
    }

    public function send(Channel $channel, ChannelContext $context, OutgoingMessage $message): void
    {
        $actions = [];
        foreach ($message->buttons as $button) {
            if ($button->kind === MessageButton::LINK) {
                $actions[] = ['action' => 'view', 'label' => $button->label, 'url' => $button->value];
            } elseif ($button->url !== '') {
                $actions[] = ['action' => 'http', 'label' => $button->label, 'url' => $button->url, 'method' => 'POST', 'clear' => true];
            }
        }

        $payload = [
            'topic' => $channel->setting('topic'),
            'message' => mb_strcut($message->text, 0, self::MAX_BYTES),
            'tags' => ['robot'],
            'priority' => $message->buttons === [] ? 3 : 4,
        ];
        if ($message->title !== '') {
            $payload['title'] = $message->title;
        }
        if ($message->link !== '') {
            $payload['click'] = $message->link;
        }
        if ($actions !== []) {
            $payload['actions'] = array_slice($actions, 0, 3);
        }

        $headers = ['Accept' => 'application/json'];
        if ($context->secret('token') !== '') {
            $headers['Authorization'] = 'Bearer ' . $context->secret('token');
        }

        try {
            $response = $this->http->send(HttpRequest::json(rtrim(self::server($channel), '/') . '/', $payload, $headers, 15));
        } catch (HttpException $e) {
            $problem = sprintf(
                /* translators: %s: error message */
                __('The ntfy server could not be reached: %s', 'agent-studio'),
                $e->getMessage()
            );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem($problem);
        }

        if (! $response->ok()) {
            $error = $response->json()['error'] ?? '';
            $problem = sprintf(
                /* translators: 1: HTTP status, 2: error from ntfy */
                __('ntfy refused the notification (HTTP %1$d). %2$s', 'agent-studio'),
                $response->status,
                is_string($error) ? $error : ''
            );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem($problem);
        }
    }

    public function acknowledge(Channel $channel, ChannelContext $context, IncomingAction $action, string $text): void
    {
    }

    public function pairingInstructions(Channel $channel, string $code): array
    {
        return [];
    }

    private static function server(Channel $channel): string
    {
        return $channel->setting('server') !== '' ? $channel->setting('server') : self::DEFAULT_SERVER;
    }
}
