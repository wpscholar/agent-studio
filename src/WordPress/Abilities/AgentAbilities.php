<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Abilities;

use WP_Error;
use AgentStudio\Application\Dispatch\DispatchOutcome;
use AgentStudio\Application\Dispatch\DispatchTriggerHandler;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Contracts\SkillRepository;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Rest\AgentsController;

/**
 * Abilities that let other tools (an MCP client, another plugin, the REST API)
 * work with agents and skills. They are not exposed to MCP unless the site
 * owner opts in with the agent_studio_expose_abilities_to_mcp filter.
 */
final class AgentAbilities
{
    public const CATEGORY = 'agent-studio';

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly SkillRepository $skills,
        private readonly DispatchTriggerHandler $dispatch,
    ) {
    }

    public function register(): void
    {
        add_action('wp_abilities_api_categories_init', [$this, 'registerCategory']);
        add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
    }

    public function registerCategory(): void
    {
        if (! function_exists('wp_register_ability_category') || wp_has_ability_category(self::CATEGORY)) {
            return;
        }

        wp_register_ability_category(
            self::CATEGORY,
            [
                'label' => __('Agent Studio', 'agent-studio'),
                'description' => __('Abilities for working with agents and skills, and starter abilities agents can use.', 'agent-studio'),
            ]
        );
    }

    public function registerAbilities(): void
    {
        if (! function_exists('wp_register_ability')) {
            return;
        }

        $meta = static fn (bool $readonly, bool $idempotent): array => [
            'annotations' => ['readonly' => $readonly, 'destructive' => false, 'idempotent' => $idempotent],
            'show_in_rest' => true,
            /**
             * Filters whether Agent Studio abilities are public MCP tools.
             *
             * @param bool $public Default false.
             */
            'mcp' => ['public' => (bool) apply_filters('agent_studio_expose_abilities_to_mcp', false)],
        ];

        wp_register_ability(
            'agent-studio/list-agents',
            [
                'label' => __('List agents', 'agent-studio'),
                'description' => __('Lists the agents on this site with their status and triggers.', 'agent-studio'),
                'category' => self::CATEGORY,
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'agents' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'name' => ['type' => 'string'],
                                    'status' => ['type' => 'string'],
                                    'triggers' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ],
                'execute_callback' => [$this, 'listAgents'],
                'permission_callback' => [Capabilities::class, 'canManage'],
                'meta' => $meta(true, true),
            ]
        );

        wp_register_ability(
            'agent-studio/run-agent',
            [
                'label' => __('Run an agent', 'agent-studio'),
                'description' => __('Starts an agent right now, optionally with event data, and returns the run ID. The run happens in the background.', 'agent-studio'),
                'category' => self::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'agent_id' => ['type' => 'integer', 'description' => 'The agent to start.'],
                        'payload' => ['type' => 'object', 'description' => 'Event data the agent will receive.'],
                    ],
                    'required' => ['agent_id'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'run_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string'],
                    ],
                ],
                'execute_callback' => [$this, 'runAgent'],
                'permission_callback' => [Capabilities::class, 'canManage'],
                'meta' => $meta(false, false),
            ]
        );

        wp_register_ability(
            'agent-studio/get-run',
            [
                'label' => __('Get an agent run', 'agent-studio'),
                'description' => __('Returns the status, output and log of an agent run.', 'agent-studio'),
                'category' => self::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['run_id' => ['type' => 'integer']],
                    'required' => ['run_id'],
                ],
                'execute_callback' => [$this, 'getRun'],
                'permission_callback' => [Capabilities::class, 'canManage'],
                'meta' => $meta(true, true),
            ]
        );

        wp_register_ability(
            'agent-studio/list-skills',
            [
                'label' => __('List skills', 'agent-studio'),
                'description' => __('Lists the skills on this site: reusable instructions with a short description.', 'agent-studio'),
                'category' => self::CATEGORY,
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'skills' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'slug' => ['type' => 'string'],
                                    'name' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'execute_callback' => [$this, 'listSkills'],
                'permission_callback' => [Capabilities::class, 'canManage'],
                'meta' => $meta(true, true),
            ]
        );

        wp_register_ability(
            'agent-studio/load-skill',
            [
                'label' => __('Load a skill', 'agent-studio'),
                'description' => __('Returns the full instructions of a skill by its slug.', 'agent-studio'),
                'category' => self::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['slug' => ['type' => 'string']],
                    'required' => ['slug'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'instructions' => ['type' => 'string'],
                    ],
                ],
                'execute_callback' => [$this, 'loadSkill'],
                'permission_callback' => [Capabilities::class, 'canManage'],
                'meta' => $meta(true, true),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function listAgents(): array
    {
        return [
            'agents' => array_map(
                static fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'status' => $agent->status->value,
                    'triggers' => array_map(static fn ($trigger): string => $trigger->type->value, $agent->triggers),
                ],
                $this->agents->all()
            ),
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function runAgent(array $input)
    {
        $agentId = (int) ($input['agent_id'] ?? 0);
        if ($this->agents->find($agentId) === null) {
            return new WP_Error('agent_studio_agent_not_found', __('Agent not found.', 'agent-studio'));
        }

        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
        $result = $this->dispatch->handle(TriggerEvent::targeted(TriggerType::Manual, $agentId, $payload, '', 0));
        $dispatch = $result->forAgent($agentId);

        if ($dispatch === null || $dispatch->outcome !== DispatchOutcome::Queued) {
            return new WP_Error('agent_studio_run_not_started', __('The agent could not be started.', 'agent-studio'));
        }

        return ['run_id' => (int) $dispatch->runId, 'status' => 'queued'];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function getRun(array $input)
    {
        $run = $this->runs->find((int) ($input['run_id'] ?? 0));

        return $run === null ? new WP_Error('agent_studio_run_not_found', __('Run not found.', 'agent-studio')) : AgentsController::summarize($run);
    }

    /**
     * @return array<string, mixed>
     */
    public function listSkills(): array
    {
        return [
            'skills' => array_map(
                static fn ($skill): array => ['slug' => $skill->slug, 'name' => $skill->name, 'description' => $skill->description],
                $this->skills->all()
            ),
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function loadSkill(array $input)
    {
        $skill = $this->skills->findBySlug(is_string($input['slug'] ?? null) ? $input['slug'] : '');
        if ($skill === null) {
            return new WP_Error('agent_studio_skill_not_found', __('Skill not found.', 'agent-studio'));
        }

        return [
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'instructions' => $skill->body,
        ];
    }
}
