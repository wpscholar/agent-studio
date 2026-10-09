<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Rest;

use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use AgentStudio\Application\Approval\ApprovalTokens;
use AgentStudio\Application\Approval\ResolveApprovalHandler;
use AgentStudio\Application\Channels\ChannelService;
use AgentStudio\Application\Channels\ChannelTypes;
use AgentStudio\Application\Channels\InboundHandler;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\ApprovalPolicy;
use AgentStudio\Contracts\Channel\ChannelPhrases;
use AgentStudio\Contracts\Channel\ChannelProblem;
use AgentStudio\Contracts\Channel\GuidedSetup;
use AgentStudio\Contracts\Channel\ChannelType;
use AgentStudio\Contracts\Channel\InboundRequest;
use AgentStudio\Contracts\Channel\OutgoingMessage;
use AgentStudio\Contracts\ChannelRepository;
use AgentStudio\Contracts\ContactRepository;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Domain\Channel\Channel;
use AgentStudio\Domain\Channel\ChannelStatus;
use AgentStudio\Domain\Channel\Contact;
use AgentStudio\Domain\Channel\ContactStatus;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\Domain\Setup\SetupStep;
use AgentStudio\WordPress\Admin\Flash;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Urls;

/**
 * The Channels screen's API, plus the two public addresses channels call.
 *
 *   GET    /channel-types                            Kinds of channel, with their setup steps.
 *   GET    /channels                                 List.
 *   POST   /channels                                 Add one and switch it on.
 *   GET    /channels/{id}                            One, with the people who wrote to it.
 *   PUT    /channels/{id}                            Change it (switched on again if it was on).
 *   DELETE /channels/{id}                            Remove it, its people and its conversations.
 *   POST   /channels/{id}/activate|deactivate        Switch on or off.
 *   POST   /channels/{id}/test                       Send a test notification.
 *   POST   /channels/{id}/pairing                    A code that links your chat account to you.
 *   POST   /channels/{id}/authorize                  Start connecting by signing in at the service (Slack).
 *   PUT    /channels/{id}/contacts/{contact}         Allow or block someone. Body: status.
 *   DELETE /channels/{id}/contacts/{contact}         Forget someone.
 *   POST   /channels/{id}/inbound                    Public: where the service sends messages. The type checks the signature.
 *   POST   /channels/{id}/decide/{run}               Public: signed Approve/Reject links (for ntfy).
 */
final class ChannelsController
{
    public function __construct(
        private readonly ChannelRepository $channels,
        private readonly ChannelService $service,
        private readonly ChannelTypes $types,
        private readonly ContactRepository $contacts,
        private readonly AgentRepository $agents,
        private readonly InboundHandler $inbound,
        private readonly RunRepository $runs,
        private readonly ResolveApprovalHandler $approvals,
        private readonly ApprovalTokens $tokens,
        private readonly ApprovalPolicy $policy,
        private readonly ChannelPhrases $phrases,
    ) {
    }

    public const CONNECTED_ACTION = 'agent_studio_channel_connected';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_post_' . self::CONNECTED_ACTION, [$this, 'connected']);
    }

    public function registerRoutes(): void
    {
        $can = [Capabilities::class, 'canManage'];
        $ns = 'agent-studio/v1';
        $id = '/channels/(?P<id>\d+)';

        register_rest_route($ns, '/channel-types', [['methods' => 'GET', 'callback' => [$this, 'types'], 'permission_callback' => $can]]);
        register_rest_route($ns, '/channels', [
            ['methods' => 'GET', 'callback' => [$this, 'index'], 'permission_callback' => $can],
            ['methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => $can],
        ]);
        register_rest_route($ns, $id, [
            ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $can],
            ['methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => $can],
            ['methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => $can],
        ]);
        register_rest_route($ns, $id . '/(?P<switch>activate|deactivate)', [['methods' => 'POST', 'callback' => [$this, 'toggle'], 'permission_callback' => $can]]);
        register_rest_route($ns, $id . '/test', [['methods' => 'POST', 'callback' => [$this, 'test'], 'permission_callback' => $can]]);
        register_rest_route($ns, $id . '/pairing', [['methods' => 'POST', 'callback' => [$this, 'pairing'], 'permission_callback' => $can]]);
        register_rest_route($ns, $id . '/authorize', [['methods' => 'POST', 'callback' => [$this, 'authorize'], 'permission_callback' => $can]]);
        register_rest_route($ns, $id . '/contacts/(?P<contact>\d+)', [
            ['methods' => 'PUT', 'callback' => [$this, 'contact'], 'permission_callback' => $can],
            ['methods' => 'DELETE', 'callback' => [$this, 'forgetContact'], 'permission_callback' => $can],
        ]);
        register_rest_route($ns, $id . '/inbound', [['methods' => 'POST', 'callback' => [$this, 'receive'], 'permission_callback' => '__return_true']]);
        register_rest_route($ns, $id . '/decide/(?P<run>\d+)', [['methods' => 'POST', 'callback' => [$this, 'decide'], 'permission_callback' => '__return_true']]);
    }

    public function types(): WP_REST_Response
    {
        return new WP_REST_Response(array_map(
            static fn (ChannelType $type): array => [
                'key' => $type->key(),
                'label' => $type->label(),
                'description' => $type->description(),
                'capabilities' => $type->capabilities()->toArray(),
                'steps' => array_map(static fn (SetupStep $step): array => $step->toArray(), $type->setupSteps()),
                'guided' => $type instanceof GuidedSetup,
            ],
            $this->types->all()
        ));
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response(array_map(fn (Channel $channel): array => $this->summary($channel), $this->channels->all()));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function show(WP_REST_Request $request)
    {
        $channel = $this->find($request);

        return $channel instanceof WP_Error ? $channel : new WP_REST_Response($this->detail($channel));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        $type = $this->types->get((string) $request->get_param('type'));
        if ($type === null) {
            return self::error('agent_studio_channel_type', __('That kind of channel is not available.', 'agent-studio'));
        }

        $values = $this->values($request, $type, null);
        if ($values instanceof WP_Error) {
            return $values;
        }

        try {
            $channel = $this->channels->save(new Channel(
                0,
                $type->key(),
                $values['name'],
                $values['agent'],
                get_current_user_id(),
                $values['settings'],
                ChannelStatus::New,
                '',
                '',
                $values['notify']
            ));
            $this->service->saveSecrets($channel, $values['secrets']);
        } catch (RuntimeException $e) {
            return self::error('agent_studio_channel_not_saved', $e->getMessage(), 500);
        }

        // Guided types finish setting up on the channel's page, so there is nothing to switch on yet.
        return new WP_REST_Response($this->detail($type instanceof GuidedSetup ? $channel : $this->service->activate($channel)), 201);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        $channel = $this->find($request);
        if ($channel instanceof WP_Error) {
            return $channel;
        }

        $values = $this->values($request, $this->service->type($channel), $channel);
        if ($values instanceof WP_Error) {
            return $values;
        }

        $reconnect = $values['settings'] !== $channel->settings || $values['secrets'] !== [];
        $channel = $this->channels->save($channel->with([
            'name' => $values['name'],
            'agentId' => $values['agent'],
            'settings' => $values['settings'],
            'notify' => $values['notify'],
        ]));
        $this->service->saveSecrets($channel, $values['secrets']);

        if ($reconnect && in_array($channel->status, [ChannelStatus::Active, ChannelStatus::Problem], true)) {
            $channel = $this->service->activate($channel);
        }

        return new WP_REST_Response($this->detail($channel));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function destroy(WP_REST_Request $request)
    {
        $channel = $this->find($request);
        if ($channel instanceof WP_Error) {
            return $channel;
        }

        $this->service->delete($channel);

        return new WP_REST_Response(['deleted' => true]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function toggle(WP_REST_Request $request)
    {
        $channel = $this->find($request);
        if ($channel instanceof WP_Error) {
            return $channel;
        }

        $channel = $request->get_param('switch') === 'activate' ? $this->service->activate($channel) : $this->service->deactivate($channel);

        return new WP_REST_Response($this->detail($channel));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function test(WP_REST_Request $request)
    {
        $channel = $this->find($request);
        if ($channel instanceof WP_Error) {
            return $channel;
        }

        $sent = $this->service->send($channel, new OutgoingMessage(
            $channel->notifyTo,
            $this->phrases->testMessage($this->service->context($channel)->siteName),
            __('Agent Studio', 'agent-studio')
        ));

        if (! $sent) {
            $fresh = $this->channels->find($channel->id) ?? $channel;

            return self::error(
                'agent_studio_channel_test_failed',
                $fresh->message !== '' ? $fresh->message : __('The test could not be sent. Is the channel switched on?', 'agent-studio'),
                502
            );
        }

        return new WP_REST_Response(['sent' => true]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function pairing(WP_REST_Request $request)
    {
        $channel = $this->find($request);
        if ($channel instanceof WP_Error) {
            return $channel;
        }

        return new WP_REST_Response($this->service->startPairing($channel, get_current_user_id()) + ['expiresIn' => ChannelService::PAIRING_TTL]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function authorize(WP_REST_Request $request)
    {
        $channel = $this->find($request);
        if ($channel instanceof WP_Error) {
            return $channel;
        }

        try {
            return new WP_REST_Response(['url' => $this->service->beginAuthorization($channel, get_current_user_id())]);
        } catch (ChannelProblem $e) {
            return self::error('agent_studio_channel_not_ready', $e->getMessage(), 409);
        }
    }

    /**
     * Where the person lands after approving the connection at the service.
     */
    public function connected(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to do that.', 'agent-studio'), '', ['response' => 403]);
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- The state parameter is the CSRF check here.
        $query = [];
        foreach ($_GET as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $query[sanitize_key($key)] = sanitize_text_field(wp_unslash($value));
            }
        }
        // phpcs:enable

        if (($query['error'] ?? '') !== '') {
            Flash::redirect(Urls::channels(), 'error', sprintf(
                /* translators: %s: error from the service */
                __('Connecting did not finish: %s', 'agent-studio'),
                $query['error']
            ));
        }

        try {
            $channel = $this->service->finishAuthorization($query['state'] ?? '', $query, get_current_user_id());
        } catch (ChannelProblem $e) {
            Flash::redirect(Urls::channels(), 'error', $e->getMessage());
        }

        Flash::redirect(
            Urls::channel($channel->id),
            $channel->isActive() ? 'success' : 'error',
            $channel->isActive() ? __('Connected and switched on.', 'agent-studio') : $channel->message
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function contact(WP_REST_Request $request)
    {
        $channel = $this->find($request);
        $contact = $this->contacts->find((int) $request->get_param('contact'));
        if ($channel instanceof WP_Error || $contact === null || $contact->channelId !== $channel->id) {
            return self::error('agent_studio_contact_not_found', __('That person is not on this channel.', 'agent-studio'), 404);
        }

        $status = ContactStatus::tryFrom((string) $request->get_param('status'));
        if ($status === null) {
            return self::error('agent_studio_contact_invalid', __('Choose allow or block.', 'agent-studio'));
        }

        $this->contacts->save($contact->withStatus($status));

        return new WP_REST_Response($this->detail($channel));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function forgetContact(WP_REST_Request $request)
    {
        $channel = $this->find($request);
        $contact = $this->contacts->find((int) $request->get_param('contact'));
        if ($channel instanceof WP_Error || $contact === null || $contact->channelId !== $channel->id) {
            return self::error('agent_studio_contact_not_found', __('That person is not on this channel.', 'agent-studio'), 404);
        }

        $this->contacts->delete($contact->id);

        return new WP_REST_Response($this->detail($channel));
    }

    /**
     * Messages and button presses from the channel's service.
     */
    public function receive(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $this->channels->find((int) $request->get_param('id'));
        if ($channel === null) {
            return new WP_REST_Response(['ok' => false], 404);
        }

        $headers = [];
        foreach ($request->get_headers() as $name => $values) {
            $headers[strtolower(str_replace('_', '-', (string) $name))] = is_array($values) ? implode(', ', $values) : (string) $values;
        }

        $query = [];
        foreach ($request->get_query_params() as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $query[$key] = (string) $value;
            }
        }

        try {
            $result = $this->inbound->handle($channel, new InboundRequest($headers, (string) $request->get_body(), $query));
        } catch (Throwable) {
            // Tell the service we got it, or it keeps retrying; the problem is ours to fix.
            return new WP_REST_Response(['ok' => true], 200);
        }

        $body = $result->contentType === 'application/json' ? json_decode($result->body, true) : $result->body;

        return new WP_REST_Response($body, $result->status);
    }

    /**
     * Approve or Reject from a signed link, as the person the channel belongs to.
     */
    public function decide(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $this->channels->find((int) $request->get_param('id'));
        $run = $this->runs->find((int) $request->get_param('run'));
        $approve = $request->get_param('decision') === 'approve';
        $token = (string) $request->get_param('token');

        if ($channel === null || $run === null || ! $this->tokens->verifyLink($run, $approve, $channel->id, $channel->ownerId, $token)) {
            return new WP_REST_Response($this->phrases->buttonExpired(), 403);
        }

        $agent = $this->agents->find($run->agentId());
        if ($run->status() !== RunStatus::AwaitingApproval || $agent === null) {
            return new WP_REST_Response($this->phrases->alreadyDecided(), 409);
        }
        if (! $this->policy->canApprove($agent, $channel->ownerId)) {
            return new WP_REST_Response($this->phrases->notAllowedToApprove(), 403);
        }

        $done = $this->approvals->handle((int) $run->id(), $approve, $channel->ownerId);

        return new WP_REST_Response($done ? $this->phrases->decided($approve) : $this->phrases->alreadyDecided(), $done ? 200 : 409);
    }

    /**
     * @return array{name: string, agent: int, settings: array<string, string>, secrets: array<string, string>, notify: bool}|WP_Error
     */
    private function values(WP_REST_Request $request, ChannelType $type, ?Channel $current)
    {
        $name = trim(sanitize_text_field((string) ($request->get_param('name') ?? $current?->name ?? '')));
        if ($name === '') {
            return self::error('agent_studio_channel_invalid', __('Give the channel a name, like “Support bot”.', 'agent-studio'));
        }

        $agentId = (int) ($request->get_param('agent_id') ?? $current?->agentId ?? 0);
        if ($type->capabilities()->receive && ($agentId <= 0 || $this->agents->find($agentId) === null)) {
            return self::error('agent_studio_channel_invalid', __('Choose which agent people will talk to here.', 'agent-studio'));
        }
        if (! $type->capabilities()->receive) {
            $agentId = 0;
        }

        $input = $request->get_param('settings');
        $input = is_array($input) ? $input : [];
        $secretInput = $request->get_param('secrets');
        $secretInput = is_array($secretInput) ? $secretInput : [];

        // Steps on the channel's page (guided setup) are filled in later, so only the first page's fields are required.
        $requiredKeys = [];
        foreach ($type->setupSteps() as $step) {
            foreach ($step->fields as $field) {
                if ($field->required) {
                    $requiredKeys[] = $field->key;
                }
            }
        }

        $settings = $current?->settings ?? [];
        $secrets = [];
        foreach ($this->service->fields($type, $current) as $field) {
            $required = in_array($field->key, $requiredKeys, true);

            if ($field->isSecret()) {
                $value = $field->clean($secretInput[$field->key] ?? '');
                if ($value !== '') {
                    $secrets[$field->key] = $value;
                } elseif ($required && $current === null) {
                    return self::error('agent_studio_channel_invalid', sprintf(
                        /* translators: %s: field label */
                        __('Fill in “%s”.', 'agent-studio'),
                        $field->label
                    ));
                }
                continue;
            }

            if (array_key_exists($field->key, $input) || ($current === null && $field->default !== '')) {
                $settings[$field->key] = $field->clean($input[$field->key] ?? $field->default);
            }
            if ($required && ($settings[$field->key] ?? '') === '') {
                return self::error('agent_studio_channel_invalid', sprintf(
                    /* translators: %s: field label */
                    __('Fill in “%s”.', 'agent-studio'),
                    $field->label
                ));
            }
        }

        $notify = $request->get_param('notify');

        return [
            'name' => $name,
            'agent' => $agentId,
            'settings' => $settings,
            'secrets' => $secrets,
            'notify' => $notify === null ? ($current?->notify ?? true) : (bool) $notify,
        ];
    }

    /**
     * @return Channel|WP_Error
     */
    private function find(WP_REST_Request $request)
    {
        $channel = $this->channels->find((int) $request->get_param('id'));

        return $channel ?? self::error('agent_studio_channel_not_found', __('That channel no longer exists.', 'agent-studio'), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Channel $channel): array
    {
        $type = $this->types->get($channel->type);
        $agent = $channel->agentId > 0 ? $this->agents->find($channel->agentId) : null;
        $owner = get_userdata($channel->ownerId);
        $people = $this->contacts->forChannel($channel->id);

        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'type' => $channel->type,
            'typeLabel' => $type?->label() ?? $channel->type,
            'capabilities' => $type?->capabilities()->toArray() ?? [],
            'agent' => $agent === null ? null : ['id' => $agent->id, 'name' => $agent->name, 'active' => $agent->isActive()],
            'owner' => $owner === false ? null : ['id' => $owner->ID, 'name' => $owner->display_name],
            'status' => $channel->status->value,
            'message' => $channel->message,
            'notify' => $channel->notify,
            'notifyReady' => $channel->notifyTo !== '' || ! ($type?->capabilities()->receive ?? false),
            'pendingCount' => count(array_filter($people, static fn (Contact $c): bool => $c->status === ContactStatus::Pending)),
            'allowedCount' => count(array_filter($people, static fn (Contact $c): bool => $c->isAllowed())),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Channel $channel): array
    {
        $type = $this->types->get($channel->type);
        $currentUser = get_current_user_id();

        return $this->summary($channel) + [
            'settings' => $channel->settings,
            'savedSecrets' => $type === null ? [] : $this->service->savedSecrets($channel),
            'steps' => $type === null ? [] : array_map(static fn (SetupStep $step): array => $step->toArray(), $type->setupSteps()),
            'guided' => $type instanceof GuidedSetup,
            'followUp' => $type === null ? [] : array_map(static fn (SetupStep $step): array => $step->toArray(), $this->service->followUpSteps($channel)),
            'linkedToMe' => count(array_filter(
                $this->contacts->forChannel($channel->id),
                static fn (Contact $c): bool => $c->userId === $currentUser && $c->isAllowed()
            )) > 0,
            'people' => array_map(
                static function (Contact $contact): array {
                    $user = $contact->userId > 0 ? get_userdata($contact->userId) : false;

                    return [
                        'id' => $contact->id,
                        'name' => $contact->name !== '' ? $contact->name : $contact->externalId,
                        'status' => $contact->status->value,
                        'user' => $user === false ? null : ['id' => $user->ID, 'name' => $user->display_name],
                        'lastSeen' => $contact->lastSeenAt,
                    ];
                },
                $this->contacts->forChannel($channel->id)
            ),
        ];
    }

    private static function error(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}
