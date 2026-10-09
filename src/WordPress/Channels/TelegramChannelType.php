<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Channels;

use AgentStudio\Contracts\Channel\ChannelContext;
use AgentStudio\Contracts\Channel\ChannelProblem;
use AgentStudio\Contracts\Channel\ChannelType;
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
 * A Telegram bot: people chat with the agent, and approval requests arrive
 * with Approve and Reject buttons.
 *
 * Telegram calls this site's webhook for every message. Each call carries a
 * secret header derived from the bot token, so only Telegram can call it.
 */
final class TelegramChannelType implements ChannelType
{
    public const KEY = 'telegram';
    private const API = 'https://api.telegram.org/bot';
    private const MAX_CHARS = 4000;

    public function __construct(private readonly HttpClient $http)
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Telegram';
    }

    public function description(): string
    {
        return __('Chat with an agent from Telegram on your phone or computer, and approve its actions with a tap. You create your own bot in a couple of minutes.', 'agent-studio');
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(receive: true, reply: true, notify: true, approve: true);
    }

    public function setupSteps(): array
    {
        return [
            new SetupStep(
                __('Create a Telegram bot', 'agent-studio'),
                [
                    __('In Telegram, open a chat with @BotFather (https://t.me/BotFather), Telegram’s official bot for making bots.', 'agent-studio'),
                    __('Send /newbot. Choose a display name, then a username that ends in “bot”, like “acme_helper_bot”.', 'agent-studio'),
                    __('BotFather replies with a token that looks like 123456789:AAE-xyz…. Copy it into the box below.', 'agent-studio'),
                ],
                [
                    new SetupField(
                        'token',
                        __('Bot token', 'agent-studio'),
                        SetupField::SECRET,
                        __('Kept encrypted. Anyone with this token can control your bot, so do not share it.', 'agent-studio'),
                        true,
                        '123456789:AAE…'
                    ),
                ]
            ),
            new SetupStep(
                __('Switch it on', 'agent-studio'),
                [
                    __('Agent Studio checks the token and asks Telegram to send your bot’s messages to this site. Your site must be reachable from the internet over https.', 'agent-studio'),
                    __('Then link your own Telegram account with the code shown, so you can chat and get approval requests.', 'agent-studio'),
                ]
            ),
        ];
    }

    public function activate(Channel $channel, ChannelContext $context): array
    {
        $token = $context->secret('token');
        if (preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $token) !== 1) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('That does not look like a bot token. Copy the whole token BotFather sent, including the part before the colon.', 'agent-studio'));
        }
        if (! str_starts_with($context->inboundUrl, 'https://')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('Telegram only sends messages to sites that use https. This site’s address starts with http://, so Telegram cannot reach it.', 'agent-studio'));
        }

        $me = $this->call($token, 'getMe', []);
        $this->call($token, 'setWebhook', [
            'url' => $context->inboundUrl,
            'secret_token' => self::webhookSecret($token),
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => true,
        ]);

        return [
            'bot_username' => is_string($me['username'] ?? null) ? $me['username'] : '',
            'bot_name' => is_string($me['first_name'] ?? null) ? $me['first_name'] : '',
        ];
    }

    public function deactivate(Channel $channel, ChannelContext $context): void
    {
        $token = $context->secret('token');
        if ($token !== '') {
            $this->call($token, 'deleteWebhook', []);
        }
    }

    public function receive(Channel $channel, ChannelContext $context, InboundRequest $request): InboundResult
    {
        $token = $context->secret('token');
        if ($token === '' || ! hash_equals(self::webhookSecret($token), $request->header('x-telegram-bot-api-secret-token'))) {
            return InboundResult::rejected();
        }

        $update = $request->json() ?? [];

        $message = is_array($update['message'] ?? null) ? $update['message'] : null;
        if ($message !== null) {
            $from = is_array($message['from'] ?? null) ? $message['from'] : [];
            $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
            $text = is_string($message['text'] ?? null) ? $message['text'] : '';
            if (($from['is_bot'] ?? false) === true || $text === '' || ! isset($chat['id'], $from['id'])) {
                return InboundResult::accepted();
            }

            $address = (string) $chat['id'] . (isset($message['message_thread_id']) ? ':' . (int) $message['message_thread_id'] : '');

            return InboundResult::accepted([
                new IncomingMessage(
                    (string) $chat['id'] . ':' . (int) ($message['message_id'] ?? 0),
                    (string) $from['id'],
                    self::name($from),
                    $text,
                    'chat:' . $address,
                    $address,
                    ($chat['type'] ?? '') === 'private' ? (string) $chat['id'] : ''
                ),
            ]);
        }

        $callback = is_array($update['callback_query'] ?? null) ? $update['callback_query'] : null;
        if ($callback !== null && isset($callback['id'], $callback['from']['id'])) {
            $chatId = $callback['message']['chat']['id'] ?? '';
            $messageId = $callback['message']['message_id'] ?? '';

            return InboundResult::accepted([
                new IncomingAction(
                    (string) $callback['from']['id'],
                    self::name(is_array($callback['from']) ? $callback['from'] : []),
                    is_string($callback['data'] ?? null) ? $callback['data'] : '',
                    (string) $chatId,
                    $callback['id'] . '|' . $chatId . '|' . $messageId
                ),
            ]);
        }

        return InboundResult::accepted();
    }

    public function send(Channel $channel, ChannelContext $context, OutgoingMessage $message): void
    {
        if ($message->to === '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem(__('Link your Telegram account first (Channels → this channel → Link my account), so Agent Studio knows where to send notifications.', 'agent-studio'));
        }

        [$chatId, $threadId] = array_pad(explode(':', $message->to, 2), 2, '');
        $text = ($message->title !== '' ? $message->title . "\n\n" : '') . $message->text;
        $chunks = $text === '' ? [''] : mb_str_split($text, self::MAX_CHARS);

        $keyboard = [];
        foreach ($message->buttons as $button) {
            if ($button->kind === MessageButton::LINK) {
                // Telegram only opens https links.
                if (str_starts_with($button->value, 'https://')) {
                    $keyboard[] = ['text' => $button->label, 'url' => $button->value];
                }
            } else {
                $keyboard[] = ['text' => $button->label, 'callback_data' => $button->value];
            }
        }

        foreach ($chunks as $i => $chunk) {
            $params = ['chat_id' => $chatId, 'text' => $chunk, 'link_preview_options' => ['is_disabled' => true]];
            if ($threadId !== '') {
                $params['message_thread_id'] = (int) $threadId;
            }
            if ($i === count($chunks) - 1 && $keyboard !== []) {
                $params['reply_markup'] = ['inline_keyboard' => [$keyboard]];
            }

            $this->call($context->secret('token'), 'sendMessage', $params);
        }
    }

    public function acknowledge(Channel $channel, ChannelContext $context, IncomingAction $action, string $text): void
    {
        [$callbackId, $chatId, $messageId] = array_pad(explode('|', $action->reference, 3), 3, '');
        $token = $context->secret('token');

        try {
            $this->call($token, 'answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => mb_substr($text, 0, 200)]);
            if ($chatId !== '' && $messageId !== '') {
                // The buttons are spent: take them off and say what happened.
                $this->call($token, 'editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => (int) $messageId, 'reply_markup' => ['inline_keyboard' => []]]);
                $this->call($token, 'sendMessage', ['chat_id' => $chatId, 'text' => $text, 'reply_parameters' => ['message_id' => (int) $messageId]]);
            }
        } catch (ChannelProblem) {
            // The decision is made either way.
        }
    }

    public function pairingInstructions(Channel $channel, string $code): array
    {
        $username = $channel->setting('bot_username');

        return [
            'text' => '/start ' . $code,
            'link' => $username !== '' ? 'https://t.me/' . rawurlencode($username) . '?start=' . $code : '',
        ];
    }

    /**
     * Telegram sends this back with every update, so the webhook knows the call is real.
     */
    private static function webhookSecret(string $token): string
    {
        return hash_hmac('sha256', 'agent-studio-telegram-webhook', $token);
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function name(array $user): string
    {
        $name = trim((is_string($user['first_name'] ?? null) ? $user['first_name'] : '') . ' ' . (is_string($user['last_name'] ?? null) ? $user['last_name'] : ''));

        return $name !== '' ? $name : (is_string($user['username'] ?? null) ? $user['username'] : '');
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function call(string $token, string $method, array $params): array
    {
        try {
            $response = $this->http->send(HttpRequest::json(self::API . $token . '/' . $method, $params, [], 20));
        } catch (HttpException $e) {
            $problem = sprintf(
                /* translators: %s: error message */
                __('Telegram could not be reached: %s', 'agent-studio'),
                $e->getMessage()
            );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem($problem);
        }

        $data = $response->json() ?? [];
        if (($data['ok'] ?? false) !== true) {
            $description = is_string($data['description'] ?? null) ? $data['description'] : sprintf('HTTP %d', $response->status);
            $problem =
                $response->status === 401
                    ? __('Telegram did not accept the bot token. Check you copied all of it.', 'agent-studio')
                    : sprintf(
                        /* translators: %s: error from Telegram */
                        __('Telegram said: %s', 'agent-studio'),
                        $description
                    );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; the admin app shows it as text.
            throw new ChannelProblem($problem);
        }

        return is_array($data['result'] ?? null) ? $data['result'] : [];
    }
}
