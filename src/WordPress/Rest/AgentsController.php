<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Rest;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use AgentStudio\Application\Dispatch\DispatchOutcome;
use AgentStudio\Application\Dispatch\DispatchTriggerHandler;
use AgentStudio\Application\Triggers\TriggerDefinitions;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Contracts\SkillRepository;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\AgentStatus;
use AgentStudio\Domain\Agent\TriggerEvent;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Run\Run;
use AgentStudio\Domain\Run\StepKind;
use AgentStudio\Infrastructure\AgentConfigMapper;
use AgentStudio\Infrastructure\AgentPostStatus;
use AgentStudio\Infrastructure\PostTypes;
use AgentStudio\WordPress\Agents\AbilityCatalog;
use AgentStudio\WordPress\Agents\TriggerCatalog;
use AgentStudio\WordPress\AgentTemplates;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Labels;
use AgentStudio\WordPress\Urls;

/**
 * The Agents screen's API, also usable by scripts that are logged in (cookie + nonce, or an application password).
 *
 *   GET    /agents                          List agents.
 *   POST   /agents                          Create one. Body: see PUT.
 *   GET    /agents/new?template=<key>       What a new agent starts with, optionally from a template.
 *   GET    /agents/{id}                     One agent, with everything the editor shows.
 *   PUT    /agents/{id}                     Change it. Body: name, instructions, status (draft|active|paused),
 *                                           triggers [{key, config}], abilities [names], skills [slugs],
 *                                           approval, runAs, maxSteps, maxRunsPerHour, provider, model.
 *   DELETE /agents/{id}                     Delete it. Its runs stay in the log.
 *   POST   /agents/{id}/run                 Start it now, whatever its status. Body: {"payload": {...}}
 *   POST   /agents/{id}/webhook-secret      Replace its webhook secret.
 *   GET    /agent-templates                 Ready-made agents to start from.
 *   GET    /runs/{id}                       Check on a run.
 */
final class AgentsController
{
    public const NAME_MAX = 200;
    public const INSTRUCTIONS_MAX = 100000;

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly DispatchTriggerHandler $dispatch,
        private readonly ?SkillRepository $skills = null,
        private readonly ?AbilityCatalog $abilities = null,
        private readonly ?TriggerCatalog $triggers = null,
        private readonly ?TriggerDefinitions $definitions = null,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $can = [Capabilities::class, 'canManage'];
        $id = '/agents/(?P<id>\d+)';

        register_rest_route('agent-studio/v1', '/agents', [
            ['methods' => 'GET', 'callback' => [$this, 'index'], 'permission_callback' => $can],
            ['methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/agents/new', [
            ['methods' => 'GET', 'callback' => [$this, 'blank'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', $id, [
            ['methods' => 'GET', 'callback' => [$this, 'show'], 'permission_callback' => $can],
            ['methods' => 'PUT', 'callback' => [$this, 'update'], 'permission_callback' => $can],
            ['methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', $id . '/run', [
            ['methods' => 'POST', 'callback' => [$this, 'run'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', $id . '/webhook-secret', [
            ['methods' => 'POST', 'callback' => [$this, 'newSecret'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/agent-templates', [
            ['methods' => 'GET', 'callback' => [$this, 'templates'], 'permission_callback' => $can],
        ]);
        register_rest_route('agent-studio/v1', '/runs/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [$this, 'showRun'], 'permission_callback' => $can],
        ]);
    }

    public function index(): WP_REST_Response
    {
        $catalog = $this->triggerCatalog()->byKey();
        $items = [];
        foreach ($this->agents->all() as $agent) {
            $labels = [];
            foreach ($agent->triggers as $trigger) {
                $key = TriggerCatalog::keyOf($trigger);
                $labels[] = (string) ($catalog[$key]['label'] ?? TriggerCatalog::missing($key)['label']);
            }

            $post = get_post($agent->id);
            $last = $this->runs->recent(1, 0, $agent->id)[0] ?? null;
            $items[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'status' => $agent->status->value,
                // Kept for older callers, such as the Channels screen.
                'active' => $agent->isActive(),
                'summary' => self::firstLine($agent->instructions),
                'triggers' => $labels,
                'abilityCount' => count($agent->abilities),
                'lastRun' => $last === null ? null : self::runSummary($last),
                'modified' => $post instanceof WP_Post ? (int) strtotime($post->post_modified_gmt . ' UTC') : 0,
            ];
        }

        return new WP_REST_Response($items);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function show(WP_REST_Request $request)
    {
        $agent = $this->agents->find((int) $request->get_param('id'));
        if ($agent === null) {
            return self::notFound();
        }

        return new WP_REST_Response($this->detail($agent));
    }

    /**
     * A new agent's starting point: blank, or filled in from a template. Nothing is saved.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function blank(WP_REST_Request $request)
    {
        $key = sanitize_key((string) $request->get_param('template'));
        $template = $key === '' ? null : (AgentTemplates::all()[$key] ?? null);
        if ($key !== '' && $template === null) {
            return self::error('agent_studio_template_not_found', __('That template no longer exists.', 'agent-studio'), 404);
        }

        $agent = AgentConfigMapper::toAgent(
            0,
            $template['title'] ?? '',
            $template['instructions'] ?? '',
            array_merge($template['config'] ?? [], ['run_as' => get_current_user_id()]),
            get_current_user_id(),
            AgentStatus::Draft
        );

        return new WP_REST_Response($this->detail($agent));
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        return $this->persist(null, $request);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        $agent = $this->agents->find((int) $request->get_param('id'));
        if ($agent === null) {
            return self::notFound();
        }

        return $this->persist($agent, $request);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function destroy(WP_REST_Request $request)
    {
        $agent = $this->agents->find((int) $request->get_param('id'));
        if ($agent === null) {
            return self::notFound();
        }

        if (! wp_delete_post($agent->id, true)) {
            return self::error('agent_studio_agent_not_deleted', __('The agent could not be deleted. Please try again.', 'agent-studio'), 500);
        }

        return new WP_REST_Response(['deleted' => true]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function newSecret(WP_REST_Request $request)
    {
        $agent = $this->agents->find((int) $request->get_param('id'));
        if ($agent === null) {
            return self::notFound();
        }

        AgentConfigMapper::write($agent->id, array_merge(AgentConfigMapper::read($agent->id), ['webhook_secret' => wp_generate_password(40, false)]));

        $updated = $this->agents->find($agent->id);

        return $updated === null ? self::notFound() : new WP_REST_Response($this->detail($updated));
    }

    public function templates(): WP_REST_Response
    {
        $items = [];
        foreach (AgentTemplates::all() as $key => $template) {
            $items[] = ['key' => $key, 'title' => $template['title'], 'summary' => $template['summary']];
        }

        return new WP_REST_Response($items);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function run(WP_REST_Request $request)
    {
        $agentId = (int) $request->get_param('id');
        if ($this->agents->find($agentId) === null) {
            return self::notFound();
        }

        $payload = $request->get_param('payload');

        $result = $this->dispatch->handle(
            TriggerEvent::targeted(TriggerType::Manual, $agentId, is_array($payload) ? $payload : [], '', 0)
        );

        $dispatch = $result->forAgent($agentId);
        if ($dispatch === null || $dispatch->outcome !== DispatchOutcome::Queued) {
            return self::error('agent_studio_run_not_started', __('The agent could not be started.', 'agent-studio'), 409);
        }

        return new WP_REST_Response(['status' => 'queued', 'run_id' => $dispatch->runId, 'url' => Urls::run((int) $dispatch->runId)], 202);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function showRun(WP_REST_Request $request)
    {
        $run = $this->runs->find((int) $request->get_param('id'));
        if ($run === null) {
            return self::error('agent_studio_run_not_found', __('Run not found.', 'agent-studio'), 404);
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
            'status_label' => Labels::status($run->status()),
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
            'url' => Urls::run((int) $run->id()),
        ];
    }

    /**
     * Validates the editor's input, then creates or updates the agent.
     *
     * @return WP_REST_Response|WP_Error
     */
    private function persist(?Agent $existing, WP_REST_Request $request)
    {
        $name = trim(sanitize_text_field((string) $request->get_param('name')));
        $instructions = trim(str_replace("\0", '', (string) $request->get_param('instructions')));
        $statusParam = (string) ($request->get_param('status') ?? ($existing?->status->value ?? AgentStatus::Draft->value));
        $status = AgentStatus::tryFrom($statusParam);

        $triggers = $this->triggersFrom($request->get_param('triggers'));
        if ($triggers instanceof WP_Error) {
            return $triggers;
        }

        $runAs = (int) $request->get_param('runAs');
        if ($runAs <= 0) {
            $runAs = $existing !== null && $existing->runAsUserId > 0 ? $existing->runAsUserId : get_current_user_id();
        }

        $problem = match (true) {
            $name === '' => __('Give the agent a name.', 'agent-studio'),
            mb_strlen($name) > self::NAME_MAX => __('The name is too long. Keep it short, like a job title.', 'agent-studio'),
            strlen($instructions) > self::INSTRUCTIONS_MAX => __('The instructions are too long. Move reference material into skills.', 'agent-studio'),
            $status === null => __('Choose whether the agent is a draft, on, or paused.', 'agent-studio'),
            $status !== AgentStatus::Draft && $instructions === '' => __('Tell the agent what to do before switching it on.', 'agent-studio'),
            get_userdata($runAs) === false => __('The person the agent acts as no longer exists. Choose someone else under “Safety and limits”.', 'agent-studio'),
            default => null,
        };

        if ($problem !== null || $status === null) {
            return self::error('agent_studio_agent_invalid', (string) $problem);
        }

        $postData = [
            'post_type' => PostTypes::AGENT,
            'post_title' => $name,
            'post_content' => $instructions,
            'post_status' => AgentPostStatus::toPost($status),
        ];

        $postId = $existing === null
            ? wp_insert_post(wp_slash($postData + ['post_author' => get_current_user_id()]), true)
            : wp_update_post(wp_slash($postData + ['ID' => $existing->id]), true);

        if (is_wp_error($postId)) {
            return self::error('agent_studio_agent_not_saved', $postId->get_error_message(), 500);
        }

        $stored = AgentConfigMapper::read((int) $postId);
        $secret = is_string($stored['webhook_secret'] ?? null) ? $stored['webhook_secret'] : '';
        $wantsWebhook = in_array(TriggerType::Webhook->value, array_column($triggers, 'type'), true);
        if ($wantsWebhook && $secret === '') {
            $secret = wp_generate_password(40, false);
        }

        AgentConfigMapper::write(
            (int) $postId,
            [
                'triggers' => $triggers,
                'abilities' => self::strings($request->get_param('abilities')),
                'skills' => self::strings($request->get_param('skills')),
                'approval' => (string) $request->get_param('approval'),
                'run_as' => $runAs,
                'max_steps' => $request->get_param('maxSteps') ?? 10,
                'max_runs_per_hour' => $request->get_param('maxRunsPerHour') ?? 30,
                'provider' => (string) $request->get_param('provider'),
                'model' => (string) $request->get_param('model'),
                'webhook_secret' => $secret,
            ]
        );

        $agent = $this->agents->find((int) $postId);
        if ($agent === null) {
            return self::notFound();
        }

        return new WP_REST_Response($this->detail($agent), $existing === null ? 201 : 200);
    }

    /**
     * Turns the editor's [{key, config}] into stored triggers, cleaning plugin-defined filters with the plugin's own fields.
     *
     * @return list<array{type: string, config: array<string, mixed>}>|WP_Error
     */
    private function triggersFrom(mixed $raw)
    {
        $triggers = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            $key = is_array($item) && is_string($item['key'] ?? null) ? $item['key'] : '';
            $config = is_array($item) && is_array($item['config'] ?? null) ? $item['config'] : [];

            if (str_starts_with($key, TriggerCatalog::CUSTOM_PREFIX)) {
                $kind = substr($key, strlen(TriggerCatalog::CUSTOM_PREFIX));
                $definition = $this->definitions?->get($kind);
                $clean = ['kind' => $kind];
                if ($definition !== null) {
                    foreach ($definition->fields() as $field) {
                        $clean[$field->key] = $field->clean($config[$field->key] ?? $field->default);
                    }
                } else {
                    // The plugin is not active; keep what was saved so nothing is lost if it comes back.
                    $clean += $config;
                }
                $triggers[] = ['type' => TriggerType::Custom->value, 'config' => $clean];
                continue;
            }

            $type = TriggerType::tryFrom($key);
            if ($type === null || in_array($type, [TriggerType::Manual, TriggerType::Message, TriggerType::Custom], true)) {
                return self::error('agent_studio_agent_invalid', __('One of the triggers is not something that can start an agent. Remove it and add it again.', 'agent-studio'));
            }

            $triggers[] = ['type' => $type->value, 'config' => $config];
        }

        return $triggers;
    }

    /**
     * Everything the editor shows for one agent.
     *
     * @return array<string, mixed>
     */
    private function detail(Agent $agent): array
    {
        $config = $agent->id > 0 ? AgentConfigMapper::normalize(AgentConfigMapper::read($agent->id)) : [];
        $catalog = $this->triggerCatalog()->byKey();

        $triggers = [];
        $triggerTypes = [];
        $hasWebhook = false;
        foreach ($agent->triggers as $trigger) {
            $key = TriggerCatalog::keyOf($trigger);
            $settings = $trigger->config;
            unset($settings['kind']);
            // An object even when empty, so the screen always gets {} rather than [].
            $triggers[] = ['key' => $key, 'config' => (object) $settings];
            $triggerTypes[$key] = $catalog[$key] ?? TriggerCatalog::missing($key);
            $hasWebhook = $hasWebhook || $trigger->type === TriggerType::Webhook;
        }

        $secret = (string) ($config['webhook_secret'] ?? '');
        $runAs = $agent->runAsUserId > 0 ? get_userdata($agent->runAsUserId) : false;
        $owner = $agent->ownerId > 0 ? get_userdata($agent->ownerId) : false;
        $post = $agent->id > 0 ? get_post($agent->id) : null;

        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'instructions' => $agent->instructions,
            'status' => $agent->status->value,
            'triggers' => $triggers,
            'triggerTypes' => (object) $triggerTypes,
            'abilities' => $agent->abilities,
            'abilityDetails' => $this->abilities?->describeNames($agent->abilities) ?? [],
            'skills' => $agent->skills,
            'skillDetails' => $this->skillDetails($agent->skills),
            'approval' => $agent->approvalMode->value,
            'runAs' => $runAs === false ? null : ['id' => $runAs->ID, 'name' => $runAs->display_name],
            'owner' => $owner === false ? null : ['id' => $owner->ID, 'name' => $owner->display_name],
            'maxSteps' => $agent->maxSteps,
            'maxRunsPerHour' => $agent->maxRunsPerHour,
            'limits' => ['maxSteps' => Agent::MAX_STEPS, 'maxRunsPerHour' => Agent::MAX_RUNS_PER_HOUR_LIMIT],
            'provider' => $agent->provider,
            'model' => $agent->model,
            'webhook' => $agent->id > 0 && $hasWebhook && $secret !== '' ? ['url' => Urls::webhook($agent->id), 'secret' => $secret] : null,
            'recentRuns' => $agent->id > 0 ? array_map([self::class, 'runSummary'], $this->runs->recent(5, 0, $agent->id)) : [],
            'runCount' => $agent->id > 0 ? $this->runs->count($agent->id) : 0,
            'runsUrl' => $agent->id > 0 ? add_query_arg('agent', $agent->id, Urls::runs()) : '',
            'modified' => $post instanceof WP_Post ? (int) strtotime($post->post_modified_gmt . ' UTC') : 0,
        ];
    }

    /**
     * @param list<string> $slugs
     *
     * @return list<array{slug: string, name: string, description: string, missing: bool}>
     */
    private function skillDetails(array $slugs): array
    {
        $known = [];
        foreach ($this->skills?->all() ?? [] as $skill) {
            $known[$skill->slug] = $skill;
        }

        $items = [];
        foreach ($slugs as $slug) {
            $skill = $known[$slug] ?? null;
            $items[] = [
                'slug' => $slug,
                'name' => $skill?->name ?? $slug,
                'description' => $skill?->description ?? '',
                'missing' => $skill === null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private static function runSummary(Run $run): array
    {
        return [
            'id' => $run->id(),
            'status' => $run->status()->value,
            'statusLabel' => Labels::status($run->status()),
            'trigger' => Labels::trigger($run->triggerType()),
            'at' => $run->createdAt(),
            'url' => Urls::run((int) $run->id()),
        ];
    }

    private function triggerCatalog(): TriggerCatalog
    {
        return $this->triggers ?? new TriggerCatalog($this->definitions);
    }

    private static function firstLine(string $text): string
    {
        $line = trim((string) strtok(trim($text), "\n"));

        return mb_strlen($line) > 160 ? rtrim(mb_substr($line, 0, 159)) . '…' : $line;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    private static function notFound(): WP_Error
    {
        return self::error('agent_studio_agent_not_found', __('That agent no longer exists.', 'agent-studio'), 404);
    }

    private static function error(string $code, string $message, int $status = 400): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}
