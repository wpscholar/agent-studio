<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Abilities;

use WP_Error;
use WPAgents\Application\Dispatch\DispatchOutcome;
use WPAgents\Application\Dispatch\DispatchTriggerHandler;
use WPAgents\Contracts\AgentRepository;
use WPAgents\Contracts\RunRepository;
use WPAgents\Contracts\SkillRepository;
use WPAgents\Domain\Agent\Agent;
use WPAgents\Domain\Agent\TriggerEvent;
use WPAgents\Domain\Agent\TriggerType;
use WPAgents\WordPress\Capabilities;
use WPAgents\WordPress\Rest\AgentsController;

/**
 * Abilities that let other tools (an MCP client, another plugin, the REST API)
 * work with agents and skills. They are not exposed to MCP unless the site
 * owner opts in with the wpa_expose_abilities_to_mcp filter.
 */
final class AgentAbilities
{
    public const CATEGORY = 'wp-agents';

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
                'label' => __('WP Agents', 'wp-agents'),
                'description' => __('Abilities for working with agents and skills, and starter abilities agents can use.', 'wp-agents'),
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
             * Filters whether WP Agents abilities are public MCP tools.
             *
             * @param bool $public Default false.
             */
            'mcp' => ['public' => (bool) apply_filters('wpa_expose_abilities_to_mcp', false)],
        ];

        wp_register_ability(
            'wp-agents/list-agents',
            [
                'label' => __('List agents', 'wp-agents'),
                'description' => __('Lists the agents on this site with their status and triggers.', 'wp-agents'),
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
            'wp-agents/run-agent',
            [
                'label' => __('Run an agent', 'wp-agents'),
                'description' => __('Starts an agent right now, optionally with event data, and returns the run ID. The run happens in the background.', 'wp-agents'),
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
            'wp-agents/get-run',
            [
                'label' => __('Get an agent run', 'wp-agents'),
                'description' => __('Returns the status, output and log of an agent run.', 'wp-agents'),
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
            'wp-agents/list-skills',
            [
                'label' => __('List skills', 'wp-agents'),
                'description' => __('Lists the skills on this site: reusable instructions with a short description.', 'wp-agents'),
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
            'wp-agents/load-skill',
            [
                'label' => __('Load a skill', 'wp-agents'),
                'description' => __('Returns the full instructions of a skill by its slug.', 'wp-agents'),
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
            return new WP_Error('wpa_agent_not_found', __('Agent not found.', 'wp-agents'));
        }

        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
        $result = $this->dispatch->handle(TriggerEvent::targeted(TriggerType::Manual, $agentId, $payload, '', 0));
        $dispatch = $result->forAgent($agentId);

        if ($dispatch === null || $dispatch->outcome !== DispatchOutcome::Queued) {
            return new WP_Error('wpa_run_not_started', __('The agent could not be started.', 'wp-agents'));
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

        return $run === null ? new WP_Error('wpa_run_not_found', __('Run not found.', 'wp-agents')) : AgentsController::summarize($run);
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
            return new WP_Error('wpa_skill_not_found', __('Skill not found.', 'wp-agents'));
        }

        return [
            'slug' => $skill->slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'instructions' => $skill->body,
        ];
    }
}
