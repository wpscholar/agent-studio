<?php

declare(strict_types=1);

namespace AgentStudio\Application\Channels;

use AgentStudio\Contracts\Channel\AuthorizesWithOAuth;
use AgentStudio\Contracts\Channel\ChannelContext;
use AgentStudio\Contracts\Channel\ChannelEndpoints;
use AgentStudio\Contracts\Channel\ChannelProblem;
use AgentStudio\Contracts\Channel\ChannelType;
use AgentStudio\Contracts\Channel\GuidedSetup;
use AgentStudio\Contracts\Channel\OutgoingMessage;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Contracts\ConversationRepository;
use AgentStudio\Contracts\PendingSignInStore;
use AgentStudio\Contracts\SecretStore;
use AgentStudio\Domain\Channel\Channel;
use AgentStudio\Domain\Channel\ChannelStatus;
use AgentStudio\Domain\Setup\SetupField;
use AgentStudio\Domain\Setup\SetupStep;
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
     * Every setup field of the channel's type, including the steps shown after
     * the channel is created when the type has them.
     *
     * @return list<SetupField>
     */
    public function fields(ChannelType $type, ?Channel $channel = null): array
    {
        $steps = $type->setupSteps();
        if ($channel !== null && $type instanceof GuidedSetup) {
            $steps = array_merge($steps, $this->followUpSteps($channel));
        }

        $fields = [];
        foreach ($steps as $step) {
            foreach ($step->fields as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * The steps still to do on the channel's page, for types that need the channel to exist first.
     *
     * @return list<SetupStep>
     */
    public function followUpSteps(Channel $channel): array
    {
        $type = $this->type($channel);

        return $type instanceof GuidedSetup
            ? $type->followUpSteps($channel, $this->endpoints->inbound($channel), $this->endpoints->authorizationReturn())
            : [];
    }

    /**
     * Starts connecting the channel by signing in at the service. Returns where to send the person.
     */
    public function beginAuthorization(Channel $channel, int $userId): string
    {
        $type = $this->type($channel);
        if (! $type instanceof AuthorizesWithOAuth) {
            throw new ChannelProblem('This kind of channel is not connected by signing in.');
        }

        $state = bin2hex(random_bytes(16));
        $this->pending->put('connect-' . $state, ['channel' => $channel->id, 'user' => $userId], self::PAIRING_TTL);

        return $type->authorizationUrl($channel, $this->context($channel), $state);
    }

    /**
     * Finishes connecting when the person comes back, then switches the channel on.
     *
     * @param array<string, string> $query
     */
    public function finishAuthorization(string $state, array $query, int $userId): Channel
    {
        $pending = $this->pending->take('connect-' . $state);
        $channel = is_array($pending) && ($pending['user'] ?? null) === $userId ? $this->channels->find((int) ($pending['channel'] ?? 0)) : null;
        if ($channel === null) {
            throw new ChannelProblem('This connection link has expired or belongs to someone else. Start again from the channel’s page.');
        }

        $type = $this->type($channel);
        if (! $type instanceof AuthorizesWithOAuth) {
            throw new ChannelProblem('This kind of channel is not connected by signing in.');
        }

        $result = $type->completeAuthorization($channel, $this->context($channel), $query);
        foreach ($result['secrets'] as $key => $value) {
            $this->secrets->set(self::secretName($channel, $key), $value);
        }
        $channel = $this->channels->save($channel->with(['settings' => array_merge($channel->settings, $result['settings'])]));

        return $this->activate($channel);
    }

    /**
     * Saves secret field values. Empty values keep what is saved.
     *
     * @param array<string, string> $values
     */
    public function saveSecrets(Channel $channel, array $values): void
    {
        foreach ($this->fields($this->type($channel), $channel) as $field) {
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
        return new ChannelContext(
            $this->secretValues($channel),
            $this->endpoints->inbound($channel),
            $this->endpoints->siteName(),
            $this->endpoints->authorizationReturn()
        );
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
            foreach ($this->secretKeys($channel) as $key) {
                $this->secrets->delete(self::secretName($channel, $key));
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
        foreach ($this->secretKeys($channel) as $key) {
            $value = $this->secrets->get(self::secretName($channel, $key));
            if ($value !== null) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function secretKeys(Channel $channel): array
    {
        $type = $this->type($channel);
        $keys = [];
        foreach ($this->fields($type, $channel) as $field) {
            if ($field->isSecret()) {
                $keys[] = $field->key;
            }
        }

        return array_values(array_unique(array_merge($keys, $type instanceof AuthorizesWithOAuth ? $type->authorizationSecrets() : [])));
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
