<?php

declare(strict_types=1);

namespace AgentStudio\Application\Channels;

use AgentStudio\Contracts\Channel\ChannelContext;
use AgentStudio\Contracts\Channel\ChannelEndpoints;
use AgentStudio\Contracts\Channel\ChannelProblem;
use AgentStudio\Contracts\Channel\ChannelType;
use AgentStudio\Contracts\Channel\OutgoingMessage;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Contracts\ConversationRepository;
use AgentStudio\Contracts\PendingSignInStore;
use AgentStudio\Contracts\SecretStore;
use AgentStudio\Domain\Channel\Channel;
use AgentStudio\Domain\Channel\ChannelStatus;
use AgentStudio\Domain\Setup\SetupField;
use RuntimeException;
use Throwable;

/**
 * Setting channels up, switching them on and off, and sending through them.
 */
final class ChannelService
{
    public const PAIRING_TTL = 1800;

    public function __construct(
        private readonly ChannelRepository $channels,
        private readonly ChannelTypes $types,
        private readonly SecretStore $secrets,
        private readonly ContactRepository $contacts,
        private readonly ConversationRepository $conversations,
        private readonly PendingSignInStore $pending,
        private readonly ChannelEndpoints $endpoints,
    ) {
    }

    public function type(Channel $channel): ChannelType
    {
        $type = $this->types->get($channel->type);
        if ($type === null) {
            throw new ChannelProblem('The plugin that provides this kind of channel is not active.');
        }

        return $type;
    }

    /**
     * Every setup field of the channel's type.
     *
     * @return list<SetupField>
     */
    public function fields(ChannelType $type): array
    {
        $fields = [];
        foreach ($type->setupSteps() as $step) {
            foreach ($step->fields as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Saves secret field values. Empty values keep what is saved.
     *
     * @param array<string, string> $values
     */
    public function saveSecrets(Channel $channel, array $values): void
    {
        foreach ($this->fields($this->type($channel)) as $field) {
            $value = $values[$field->key] ?? '';
            if ($field->isSecret() && $value !== '') {
                $this->secrets->set(self::secretName($channel, $field->key), $value);
            }
        }
    }

    /**
     * Which secret fields have a saved value.
     *
     * @return list<string>
     */
    public function savedSecrets(Channel $channel): array
    {
        return array_keys($this->secretValues($channel));
    }

    public function context(Channel $channel): ChannelContext
    {
        return new ChannelContext($this->secretValues($channel), $this->endpoints->inbound($channel), $this->endpoints->siteName());
    }

    /**
     * Connects the channel to its service. On failure the channel is saved with
     * the problem so the screen can explain it.
     */
    public function activate(Channel $channel): Channel
    {
        try {
            $type = $this->type($channel);
            $settings = $type->activate($channel, $this->context($channel));

            return $this->channels->save($channel->with([
                'settings' => array_merge($channel->settings, $settings),
                'status' => ChannelStatus::Active,
                'message' => '',
            ]));
        } catch (ChannelProblem $e) {
            return $this->channels->save($channel->with(['status' => ChannelStatus::Problem, 'message' => $e->getMessage()]));
        }
    }

    public function deactivate(Channel $channel): Channel
    {
        try {
            $this->type($channel)->deactivate($channel, $this->context($channel));
        } catch (Throwable) {
            // Switching off must always work, even when the service is unreachable.
        }

        return $this->channels->save($channel->with(['status' => ChannelStatus::Off, 'message' => '']));
    }

    public function delete(Channel $channel): void
    {
        if ($channel->isActive()) {
            $this->deactivate($channel);
        }

        try {
            foreach ($this->fields($this->type($channel)) as $field) {
                $this->secrets->delete(self::secretName($channel, $field->key));
            }
        } catch (ChannelProblem) {
            // The type's plugin is gone; its secrets cannot be listed. They stay unreadable without it.
        }

        $this->contacts->deleteForChannel($channel->id);
        $this->conversations->deleteForChannel($channel->id);
        $this->channels->delete($channel->id);
    }

    /**
     * Sends a message. Returns false (and notes the problem on the channel) when it could not be sent.
     */
    public function send(Channel $channel, OutgoingMessage $message): bool
    {
        if (! $channel->isActive()) {
            return false;
        }

        try {
            $this->type($channel)->send($channel, $this->context($channel), $message);

            return true;
        } catch (ChannelProblem $e) {
            $this->channels->save($channel->with(['message' => $e->getMessage()]));

            return false;
        }
    }

    /**
     * Starts linking a chat account to the person's WordPress account: they send
     * the code to the channel within half an hour.
     *
     * @return array{code: string, text?: string, link?: string}
     */
    public function startPairing(Channel $channel, int $userId): array
    {
        $code = (string) random_int(100000, 999999);
        $this->pending->put(self::pairingKey($channel, $code), ['user' => $userId], self::PAIRING_TTL);

        return ['code' => $code] + $this->type($channel)->pairingInstructions($channel, $code);
    }

    /**
     * The WordPress user a pairing code belongs to, once. Null when unknown or expired.
     */
    public function takePairing(Channel $channel, string $code): ?int
    {
        $data = $this->pending->take(self::pairingKey($channel, $code));

        return is_int($data['user'] ?? null) ? $data['user'] : null;
    }

    /**
     * @return array<string, string>
     */
    private function secretValues(Channel $channel): array
    {
        $values = [];
        foreach ($this->fields($this->type($channel)) as $field) {
            if (! $field->isSecret()) {
                continue;
            }
            $value = $this->secrets->get(self::secretName($channel, $field->key));
            if ($value !== null) {
                $values[$field->key] = $value;
            }
        }

        return $values;
    }

    private static function secretName(Channel $channel, string $key): string
    {
        if ($channel->id <= 0) {
            throw new RuntimeException('Save the channel before storing its secrets.');
        }

        return 'channel-' . $channel->id . '-' . str_replace('_', '-', $key);
    }

    private static function pairingKey(Channel $channel, string $code): string
    {
        return 'pair-' . $channel->id . '-' . $code;
    }
}
