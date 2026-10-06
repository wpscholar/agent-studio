<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPAgents\Application\Dispatch\DispatchOutcome;
use WPAgents\Application\Dispatch\DispatchTriggerHandler;
use WPAgents\Contracts\AgentRepository;
use WPAgents\Contracts\Clock;
use WPAgents\Domain\Agent\TriggerEvent;
use WPAgents\Domain\Agent\TriggerType;

/**
 * POST /wp-json/wp-agents/v1/webhooks/{agent_id}
 *
 * Lets an outside system start an agent. There is no login: the caller proves
 * who it is by signing the request with the agent's secret.
 *
 *   X-WPA-Timestamp: <unix seconds, within 5 minutes of now>
 *   X-WPA-Signature: sha256=<hex HMAC-SHA256 of "{timestamp}.{raw body}" using the secret>
 *   Idempotency-Key: <optional; the same key within 24 hours starts the agent once>
 */
final class WebhookController
{
    public const MAX_BODY_BYTES = 262144;
    public const MAX_CLOCK_SKEW = 300;
    private const IDEMPOTENCY_WINDOW = 86400;

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly DispatchTriggerHandler $dispatch,
        private readonly Clock $clock,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'wp-agents/v1',
            '/webhooks/(?P<agent>\d+)',
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle'],
                // Public on purpose. The request is authenticated by its HMAC signature in handle().
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function handle(WP_REST_Request $request)
    {
        $agent = $this->agents->find((int) $request->get_param('agent'));

        // Every reason an agent cannot take webhooks looks the same from the outside.
        if ($agent === null || ! $agent->isActive() || ! $agent->hasTrigger(TriggerType::Webhook) || $agent->webhookSecret === '') {
            return new WP_Error('wpa_webhook_not_found', __('Webhook not found.', 'wp-agents'), ['status' => 404]);
        }

        $body = $request->get_body();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            return new WP_Error('wpa_webhook_too_large', __('The request body is too large.', 'wp-agents'), ['status' => 413]);
        }

        $timestamp = (string) $request->get_header('x_wpa_timestamp');
        $signature = (string) $request->get_header('x_wpa_signature');

        if (! self::isValidSignature($agent->webhookSecret, $timestamp, $body, $signature, $this->clock->now())) {
            return new WP_Error('wpa_webhook_unauthorized', __('The signature is missing or invalid.', 'wp-agents'), ['status' => 401]);
        }

        $key = (string) $request->get_header('idempotency_key');
        $dedupeKey = $key !== '' ? 'webhook:' . substr(sanitize_text_field($key), 0, 150) : '';

        $result = $this->dispatch->handle(
            TriggerEvent::targeted(TriggerType::Webhook, $agent->id, self::payload($body), $dedupeKey, self::IDEMPOTENCY_WINDOW)
        );

        $dispatch = $result->forAgent($agent->id);

        if ($dispatch === null) {
            return new WP_REST_Response(['status' => 'ignored'], 202);
        }

        return match ($dispatch->outcome) {
            DispatchOutcome::Queued => new WP_REST_Response(['status' => 'queued', 'run_id' => $dispatch->runId], 202),
            DispatchOutcome::Duplicate => new WP_REST_Response(['status' => 'duplicate', 'run_id' => $dispatch->runId], 200),
            DispatchOutcome::RateLimited => new WP_Error('wpa_webhook_rate_limited', __('This agent has reached its hourly run limit.', 'wp-agents'), ['status' => 429]),
            DispatchOutcome::Skipped => new WP_Error('wpa_webhook_not_found', __('Webhook not found.', 'wp-agents'), ['status' => 404]),
        };
    }

    /**
     * Whether the signature proves the sender knows the secret and the request is fresh.
     */
    public static function isValidSignature(string $secret, string $timestamp, string $body, string $signature, int $now): bool
    {
        if ($secret === '' || ! ctype_digit($timestamp) || abs($now - (int) $timestamp) > self::MAX_CLOCK_SKEW) {
            return false;
        }

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        return hash_equals($expected, substr($signature, 7));
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(string $body): array
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return ['raw_body' => mb_substr($body, 0, 10000)];
    }
}
