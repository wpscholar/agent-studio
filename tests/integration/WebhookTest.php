<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class WebhookTest extends IntegrationTestCase
{
    private const SECRET = 'abcDEF1234567890abcDEF1234567890abcDEF12';

    private int $agentId = 0;

    public function set_up(): void
    {
        parent::set_up();

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        $this->agentId = $this->createAgent(['triggers' => [['type' => 'webhook']], 'webhook_secret' => self::SECRET], 'Hook');
        wp_set_current_user(0);
    }

    private function call(string $body, ?string $signature = null, ?int $timestamp = null, string $key = ''): WP_REST_Response
    {
        $timestamp ??= time();
        $request = new WP_REST_Request('POST', '/agent-studio/v1/webhooks/' . $this->agentId);
        $request->set_body($body);
        $request->set_header('Content-Type', 'application/json');
        $request->set_header('X-Agent-Studio-Timestamp', (string) $timestamp);
        $request->set_header('X-Agent-Studio-Signature', $signature ?? 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, self::SECRET));
        if ($key !== '') {
            $request->set_header('Idempotency-Key', $key);
        }

        return rest_do_request($request);
    }

    public function test_a_correctly_signed_request_queues_a_run(): void
    {
        $response = $this->call('{"order":42}');

        $this->assertSame(202, $response->get_status());
        $run = $this->runs->find((int) $response->get_data()['run_id']);
        $this->assertSame(42, $run->input()['order']);
    }

    public function test_a_wrong_signature_is_rejected(): void
    {
        $this->assertSame(401, $this->call('{"a":1}', 'sha256=deadbeef')->get_status());
    }

    public function test_an_old_request_is_rejected_even_with_a_valid_signature(): void
    {
        $this->assertSame(401, $this->call('{"a":1}', null, time() - 4000)->get_status());
    }

    public function test_the_same_idempotency_key_does_not_start_a_second_run(): void
    {
        $first = $this->call('{"a":1}', null, null, 'key-1');
        $second = $this->call('{"a":1}', null, null, 'key-1');

        $this->assertSame(202, $first->get_status());
        $this->assertSame(200, $second->get_status());
        $this->assertSame($first->get_data()['run_id'], $second->get_data()['run_id']);
        $this->assertCount(1, $this->runs->recent(10, 0, $this->agentId));
    }

    public function test_an_agent_without_a_webhook_trigger_is_not_found(): void
    {
        $other = $this->createAgent(['triggers' => [['type' => 'user_registered']]], 'No hook');
        $request = new WP_REST_Request('POST', '/agent-studio/v1/webhooks/' . $other);
        $request->set_body('{}');

        $this->assertTrue(in_array(rest_do_request($request)->get_status(), [401, 404], true));
    }

    public function test_direct_runs_need_a_logged_in_manager(): void
    {
        $this->assertSame(401, rest_do_request(new WP_REST_Request('POST', '/agent-studio/v1/agents/' . $this->agentId . '/run'))->get_status());

        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertSame(403, rest_do_request(new WP_REST_Request('POST', '/agent-studio/v1/agents/' . $this->agentId . '/run'))->get_status());

        wp_set_current_user($this->adminId);
        $this->assertSame(202, rest_do_request(new WP_REST_Request('POST', '/agent-studio/v1/agents/' . $this->agentId . '/run'))->get_status());
    }
}
