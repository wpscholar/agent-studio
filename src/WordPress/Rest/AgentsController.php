<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use AgentStudio\Application\Dispatch\DispatchOutcome;
use AgentStudio\Application\Dispatch\DispatchTriggerHandler;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\StepKind;
use AgentStudio\WordPress\Capabilities;

/**
 * Direct calls for people and scripts that are logged in (cookie + nonce, or an application password).
 *
 *   GET  /wp-json/agent-studio/v1/agents            List agents (id, name, active).
 *   POST /wp-json/agent-studio/v1/agents/{id}/run   Start an agent now. Body: {"payload": {...}}
 *   GET  /wp-json/agent-studio/v1/runs/{id}         Check on a run.
 */
final class AgentsController
{
    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly DispatchTriggerHandler $dispatch,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'agent-studio/v1',
            '/agents',
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [Capabilities::class, 'canManage'],
            ]
        );

        register_rest_route(
            'agent-studio/v1',
            '/agents/(?P<id>\d+)/run',
            [
                'methods' => 'POST',
                'callback' => [$this, 'run'],
                'permission_callback' => [Capabilities::class, 'canManage'],
            ]
        );

        register_rest_route(
            'agent-studio/v1',
            '/runs/(?P<id>\d+)',
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [Capabilities::class, 'canManage'],
            ]
        );
    }

    public function index(): WP_REST_Response
    {
        $agents = [];
        foreach ($this->agents->all() as $agent) {
            $agents[] = ['id' => $agent->id, 'name' => $agent->name, 'active' => $agent->isActive()];
        }

        return new WP_REST_Response($agents);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function run(WP_REST_Request $request)
    {
        $agentId = (int) $request->get_param('id');
        if ($this->agents->find($agentId) === null) {
            return new WP_Error('agent_studio_agent_not_found', __('Agent not found.', 'agent-studio'), ['status' => 404]);
        }

        $payload = $request->get_param('payload');

        $result = $this->dispatch->handle(
            TriggerEvent::targeted(TriggerType::Manual, $agentId, is_array($payload) ? $payload : [], '', 0)
        );

        $dispatch = $result->forAgent($agentId);
        if ($dispatch === null || $dispatch->outcome !== DispatchOutcome::Queued) {
            return new WP_Error('agent_studio_run_not_started', __('The agent could not be started.', 'agent-studio'), ['status' => 409]);
        }

        return new WP_REST_Response(['status' => 'queued', 'run_id' => $dispatch->runId], 202);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function show(WP_REST_Request $request)
    {
        $run = $this->runs->find((int) $request->get_param('id'));
        if ($run === null) {
            return new WP_Error('agent_studio_run_not_found', __('Run not found.', 'agent-studio'), ['status' => 404]);
        }

        return new WP_REST_Response(self::summarize($run));
    }

    /**
     * A compact, safe description of a run (no raw conversation).
     *
     * @return array<string, mixed>
     */
    public static function summarize(Run $run): array
    {
        $steps = [];
        foreach ($run->steps() as $step) {
            if ($step->kind !== StepKind::Model || $step->label !== '') {
                $steps[] = ['kind' => $step->kind->value, 'label' => $step->label, 'at' => $step->at];
            }
        }

        $pending = $run->pendingApproval();

        return [
            'id' => $run->id(),
            'agent_id' => $run->agentId(),
            'trigger' => $run->triggerType()->value,
            'status' => $run->status()->value,
            'output' => $run->output(),
            'error' => $run->error(),
            'steps' => $steps,
            'pending_approval' => $pending === null ? null : [
                'ability' => $pending->abilityName,
                'risk' => $pending->risk->value,
                'input' => $pending->input,
            ],
            'tokens_in' => $run->tokensIn(),
            'tokens_out' => $run->tokensOut(),
            'created_at' => $run->createdAt(),
            'finished_at' => $run->finishedAt(),
        ];
    }
}
