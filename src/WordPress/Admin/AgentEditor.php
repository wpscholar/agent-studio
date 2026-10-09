<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use WP_Post;
use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Contracts\RunRepository;
use AgentStudio\Contracts\SkillRepository;
use AgentStudio\Domain\Agent\AgentStatus;
use AgentStudio\Domain\Agent\ApprovalMode;
use AgentStudio\Domain\Agent\Schedule;
use AgentStudio\Domain\Agent\TriggerType;
use AgentStudio\Domain\Run\RunStatus;
use AgentStudio\Infrastructure\AgentConfigMapper;
use AgentStudio\Infrastructure\AiModelCatalog;
use AgentStudio\Infrastructure\PostTypes;
use AgentStudio\WordPress\Labels;
use AgentStudio\WordPress\Urls;

/**
 * The agent edit screen: instructions in the editor, everything else in boxes.
 */
final class AgentEditor
{
    // Joins provider and model IDs in the model dropdown. Neither contains it.
    private const MODEL_SEPARATOR = '::';

    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly SkillRepository $skills,
        private readonly AbilityGateway $abilities,
        private readonly AiModelCatalog $models,
    ) {
    }

    public function register(): void
    {
        add_action('add_meta_boxes_' . PostTypes::AGENT, [$this, 'addMetaBoxes']);
        add_action('save_post_' . PostTypes::AGENT, [$this, 'save'], 10, 2);
        add_filter('manage_' . PostTypes::AGENT . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . PostTypes::AGENT . '_posts_custom_column', [$this, 'column'], 10, 2);
        add_filter('enter_title_here', [$this, 'titlePlaceholder'], 10, 2);
        add_action('edit_form_after_title', [$this, 'instructionsHint']);
        add_action('admin_notices', [Flash::class, 'render']);
    }

    public function titlePlaceholder(string $text, WP_Post $post): string
    {
        return $post->post_type === PostTypes::AGENT ? __('Agent name', 'agent-studio') : $text;
    }

    public function instructionsHint(WP_Post $post): void
    {
        if ($post->post_type !== PostTypes::AGENT) {
            return;
        }

        echo '<p class="agent-studio-hint"><strong>' . esc_html__('Instructions', 'agent-studio') . '</strong> ';
        echo esc_html__('Tell the agent what to do, in plain language. It receives the details of the event that started it.', 'agent-studio') . '</p>';
    }

    public function addMetaBoxes(): void
    {
        add_meta_box('agent-studio-triggers', __('When should this agent run?', 'agent-studio'), [$this, 'triggersBox'], PostTypes::AGENT, 'normal', 'high');
        add_meta_box('agent-studio-model', __('Which AI model should it use?', 'agent-studio'), [$this, 'modelBox'], PostTypes::AGENT, 'normal', 'high');
        add_meta_box('agent-studio-abilities', __('What can it do?', 'agent-studio'), [$this, 'abilitiesBox'], PostTypes::AGENT, 'normal', 'default');
        add_meta_box('agent-studio-skills', __('Skills', 'agent-studio'), [$this, 'skillsBox'], PostTypes::AGENT, 'normal', 'default');
        add_meta_box('agent-studio-safety', __('Safety and limits', 'agent-studio'), [$this, 'safetyBox'], PostTypes::AGENT, 'normal', 'default');
        add_meta_box('agent-studio-status', __('Status', 'agent-studio'), [$this, 'statusBox'], PostTypes::AGENT, 'side', 'high');
        add_meta_box('agent-studio-run', __('Run and webhook', 'agent-studio'), [$this, 'runBox'], PostTypes::AGENT, 'side', 'default');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(WP_Post $post): array
    {
        return AgentConfigMapper::normalize(array_merge(AgentConfigMapper::defaults(), AgentConfigMapper::read($post->ID)));
    }

    public function statusBox(WP_Post $post): void
    {
        wp_nonce_field('agent_studio_save_agent_' . $post->ID, 'agent_studio_nonce');
        $config = $this->config($post);

        echo '<p><label class="screen-reader-text" for="agent-studio-status-field">' . esc_html__('Status', 'agent-studio') . '</label>';
        echo '<select name="agent_studio[status]" id="agent-studio-status-field" class="widefat">';
        foreach ([AgentStatus::Paused->value => __('Paused', 'agent-studio'), AgentStatus::Active->value => __('Active', 'agent-studio')] as $value => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($config['status'], $value, false), esc_html($label));
        }
        echo '</select></p>';
        echo '<p class="description">' . esc_html__('A paused agent ignores events. You can still run it by hand.', 'agent-studio') . '</p>';
    }

    public function triggersBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $byType = [];
        foreach ($config['triggers'] as $trigger) {
            $byType[$trigger['type']] = $trigger['config'];
        }

        $rows = [
            TriggerType::PostStatus->value => [
                'help' => __('When a post changes status, for example when it is submitted for review or published.', 'agent-studio'),
                'fields' => [
                    'post_types' => [__('Post types', 'agent-studio'), 'post'],
                    'from' => [__('From status', 'agent-studio'), __('any', 'agent-studio')],
                    'to' => [__('To status', 'agent-studio'), 'pending, publish'],
                ],
            ],
            TriggerType::UserRegistered->value => [
                'help' => __('When someone registers or is added as a user.', 'agent-studio'),
                'fields' => ['roles' => [__('Roles', 'agent-studio'), __('any', 'agent-studio')]],
            ],
            TriggerType::CommentPosted->value => [
                'help' => __('When a new comment is left.', 'agent-studio'),
                'fields' => ['post_types' => [__('Post types', 'agent-studio'), __('any', 'agent-studio')]],
            ],
            TriggerType::Schedule->value => [
                'help' => __('On a timetable, for example every morning at 9:00 or every Monday. Good for reports, digests and regular clean-ups.', 'agent-studio'),
                'fields' => [],
            ],
            TriggerType::Webhook->value => [
                'help' => __('When another system calls this agent’s secure webhook URL.', 'agent-studio'),
                'fields' => [],
            ],
        ];

        echo '<p class="description">' . esc_html__('Choose what starts this agent. Leave a filter empty to match everything. Separate several values with commas.', 'agent-studio') . '</p>';
        echo '<div class="agent-studio-triggers">';

        foreach ($rows as $type => $row) {
            $enabled = isset($byType[$type]);
            $label = Labels::trigger(TriggerType::from($type));
            echo '<div class="agent-studio-trigger' . ($enabled ? ' is-on' : '') . '">';
            printf(
                '<label class="agent-studio-trigger__title"><input type="checkbox" name="agent_studio[triggers][%1$s][on]" value="1" data-agent-studio-trigger %2$s> <strong>%3$s</strong></label>',
                esc_attr($type),
                checked($enabled, true, false),
                esc_html($label)
            );
            echo '<p class="description">' . esc_html($row['help']) . '</p>';

            if ($type === TriggerType::Schedule->value) {
                $this->scheduleFields($byType[$type] ?? [], $enabled && $config['status'] === AgentStatus::Active->value);
            }

            if ($row['fields'] !== []) {
                echo '<div class="agent-studio-trigger__fields">';
                foreach ($row['fields'] as $key => [$fieldLabel, $placeholder]) {
                    $value = implode(', ', array_map('strval', (array) ($byType[$type][$key] ?? [])));
                    printf(
                        '<label>%1$s <input type="text" class="regular-text" name="agent_studio[triggers][%2$s][%3$s]" value="%4$s" placeholder="%5$s"></label>',
                        esc_html($fieldLabel),
                        esc_attr($type),
                        esc_attr($key),
                        esc_attr($value),
                        esc_attr($placeholder)
                    );
                }
                echo '</div>';
            }

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * @param array<string, mixed> $saved
     */
    private function scheduleFields(array $saved, bool $active): void
    {
        $schedule = Schedule::fromConfig($saved) ?? Schedule::fromConfig(['every' => Schedule::DAILY, 'at' => '09:00']);
        if ($schedule === null) {
            return;
        }

        $name = 'agent_studio[triggers][' . TriggerType::Schedule->value . ']';
        $frequencies = [
            Schedule::HOURLY => __('Every hour', 'agent-studio'),
            Schedule::DAILY => __('Every day', 'agent-studio'),
            Schedule::WEEKLY => __('Every week', 'agent-studio'),
        ];
        $days = [
            'monday' => __('Monday', 'agent-studio'),
            'tuesday' => __('Tuesday', 'agent-studio'),
            'wednesday' => __('Wednesday', 'agent-studio'),
            'thursday' => __('Thursday', 'agent-studio'),
            'friday' => __('Friday', 'agent-studio'),
            'saturday' => __('Saturday', 'agent-studio'),
            'sunday' => __('Sunday', 'agent-studio'),
        ];

        echo '<div class="agent-studio-trigger__fields" data-agent-studio-schedule>';

        printf('<label>%1$s <select name="%2$s[every]" data-agent-studio-schedule-every>', esc_html__('How often', 'agent-studio'), esc_attr($name));
        foreach ($frequencies as $value => $label) {
            printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr($value), selected($schedule->every, $value, false), esc_html($label));
        }
        echo '</select></label>';

        printf('<label data-agent-studio-schedule-day>%1$s <select name="%2$s[day]">', esc_html__('On', 'agent-studio'), esc_attr($name));
        foreach ($days as $value => $label) {
            printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr($value), selected($schedule->day, $value, false), esc_html($label));
        }
        echo '</select></label>';

        printf(
            '<label>%1$s <input type="time" name="%2$s[at]" value="%3$s" required></label>',
            esc_html__('At', 'agent-studio'),
            esc_attr($name),
            esc_attr(sprintf('%02d:%02d', $schedule->hour, $schedule->minute))
        );

        printf(
            '<p class="description agent-studio-schedule-help">%1$s <a href="%2$s">%3$s</a></p>',
            esc_html(
                sprintf(
                    /* translators: %s: the site's timezone, for example "Europe/London" or "UTC+2". */
                    __('Times use your site’s timezone (%s). For “Every hour”, only the minutes count: 9:15 means a quarter past every hour. WordPress checks schedules when people visit your site, so on a quiet site a run can start a few minutes late. The first run is the next time on the timetable after you save.', 'agent-studio'),
                    $this->timezoneLabel()
                )
            ),
            esc_url(admin_url('options-general.php')),
            esc_html__('Change the timezone', 'agent-studio')
        );

        if (isset($saved['every'])) {
            $next = $schedule->nextSlot(new \DateTimeImmutable('now', wp_timezone()));
            echo '<p class="agent-studio-schedule-next"><strong>';
            echo $active
                ? esc_html(
                    sprintf(
                        /* translators: %s: date and time of the next scheduled run. */
                        __('Next run: %s', 'agent-studio'),
                        wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next->getTimestamp())
                    )
                )
                : esc_html__('No scheduled runs while this agent is paused. Set its status to Active to start the timetable.', 'agent-studio');
            echo '</strong></p>';
        }

        echo '</div>';
    }

    private function timezoneLabel(): string
    {
        $zone = wp_timezone_string();

        // Sites set to a plain offset report "+02:00"; show it as "UTC+02:00".
        return preg_match('/^[+-]/', $zone) === 1 ? 'UTC' . $zone : $zone;
    }

    public function abilitiesBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $selected = $config['abilities'];
        $available = $this->abilities->available();

        echo '<p class="description">' . esc_html__('The agent can only use what you tick here. Abilities come from WordPress and from other plugins.', 'agent-studio') . '</p>';

        if ($available === []) {
            echo '<p>' . esc_html__('No abilities are registered on this site yet.', 'agent-studio') . '</p>';
        } else {
            echo '<p><input type="search" class="regular-text" data-agent-studio-filter placeholder="' . esc_attr__('Filter abilities…', 'agent-studio') . '"></p>';
        }

        $known = [];
        echo '<div class="agent-studio-abilities">';
        foreach ($available as $info) {
            $known[$info->name] = true;
            printf(
                '<label class="agent-studio-ability" data-agent-studio-text="%1$s"><input type="checkbox" name="agent_studio[abilities][]" value="%2$s" %3$s> <span class="agent-studio-ability__name">%4$s</span> <span class="agent-studio-risk agent-studio-risk--%5$s" title="%6$s">%7$s</span><br><span class="description"><code>%2$s</code> %8$s</span></label>',
                esc_attr(strtolower($info->name . ' ' . $info->label . ' ' . $info->description)),
                esc_attr($info->name),
                checked(in_array($info->name, $selected, true), true, false),
                esc_html($info->label !== '' ? $info->label : $info->name),
                esc_attr($info->risk->value),
                esc_attr(Labels::riskHelp($info->risk)),
                esc_html(Labels::risk($info->risk)),
                esc_html($info->description)
            );
        }
        echo '</div>';

        foreach ($selected as $name) {
            if (! isset($known[$name])) {
                printf(
                    '<label class="agent-studio-ability agent-studio-ability--missing"><input type="checkbox" name="agent_studio[abilities][]" value="%1$s" checked> <code>%1$s</code> <span class="description">%2$s</span></label>',
                    esc_attr($name),
                    esc_html__('Not registered right now. Untick it to remove.', 'agent-studio')
                );
            }
        }
    }

    public function skillsBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $skills = $this->skills->all();

        echo '<p class="description">' . esc_html__('Skills are reusable instructions. The agent sees each skill’s description and loads the full text only when it needs it.', 'agent-studio') . '</p>';

        if ($skills === []) {
            printf(
                '<p>%s <a href="%s">%s</a> · <a href="%s">%s</a></p>',
                esc_html__('No skills yet.', 'agent-studio'),
                esc_url(Urls::skill('new')),
                esc_html__('Add a skill', 'agent-studio'),
                esc_url(Urls::skill('import')),
                esc_html__('Import a SKILL.md', 'agent-studio')
            );

            return;
        }

        foreach ($skills as $skill) {
            printf(
                '<label class="agent-studio-skill"><input type="checkbox" name="agent_studio[skills][]" value="%1$s" %2$s> <strong>%3$s</strong><br><span class="description">%4$s</span></label>',
                esc_attr($skill->slug),
                checked(in_array($skill->slug, $config['skills'], true), true, false),
                esc_html($skill->name),
                esc_html($skill->description)
            );
        }
    }

    public function modelBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $current = $config['model'] === '' ? '' : $config['provider'] . self::MODEL_SEPARATOR . $config['model'];
        $providers = $this->models->providers();
        $connectorsUrl = admin_url('options-connectors.php');

        echo '<p class="description">' . esc_html__('The AI model is what reads the instructions and decides what to do. “Automatic” is right for most agents: WordPress picks one of your connected models that can use the abilities you allow below.', 'agent-studio') . '</p>';

        if ($providers === []) {
            printf(
                '<p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p>',
                esc_html__('No AI connection is set up yet.', 'agent-studio'),
                esc_html__('Agents cannot run until you connect an AI provider, such as Anthropic, Google or OpenAI.', 'agent-studio'),
                esc_url($connectorsUrl),
                esc_html__('Set one up in Settings → Connectors', 'agent-studio')
            );
        }

        echo '<p><label for="agent-studio-model" class="screen-reader-text">' . esc_html__('AI model', 'agent-studio') . '</label>';
        echo '<select id="agent-studio-model" name="agent_studio[model_choice]">';
        printf('<option value=""%1$s>%2$s</option>', selected($current, '', false), esc_html__('Automatic (recommended)', 'agent-studio'));

        $found = $current === '';
        foreach ($providers as $provider) {
            printf('<optgroup label="%s">', esc_attr($provider['name']));
            foreach ($provider['models'] as $model) {
                $value = $provider['id'] . self::MODEL_SEPARATOR . $model['id'];
                $found = $found || $value === $current;
                printf(
                    '<option value="%1$s"%2$s>%3$s</option>',
                    esc_attr($value),
                    selected($current, $value, false),
                    esc_html(
                        $model['tools']
                            ? $model['name']
                            /* translators: %s: model name. */
                            : sprintf(__('%s (cannot use abilities)', 'agent-studio'), $model['name'])
                    )
                );
            }
            echo '</optgroup>';
        }

        if (! $found) {
            printf(
                '<option value="%1$s" selected>%2$s</option>',
                esc_attr($current),
                /* translators: %s: model ID. */
                esc_html(sprintf(__('%s (not available right now)', 'agent-studio'), $config['model']))
            );
        }
        echo '</select></p>';

        echo '<p class="description">' . esc_html__('Pick a specific model only if you want this agent to always use it, for example a faster, cheaper model for simple jobs. If that model is unavailable, or cannot use the abilities this agent needs, WordPress uses another connected model instead.', 'agent-studio') . '</p>';

        if ($providers !== []) {
            printf(
                '<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
                esc_html__('Only models from your connected providers are listed.', 'agent-studio'),
                esc_url($connectorsUrl),
                esc_html__('Manage connections', 'agent-studio')
            );
        }
    }

    public function safetyBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $runAs = (int) $config['run_as'] > 0 ? (int) $config['run_as'] : get_current_user_id();

        echo '<table class="form-table agent-studio-form"><tbody>';

        echo '<tr><th scope="row"><label for="agent-studio-approval">' . esc_html__('Approval', 'agent-studio') . '</label></th><td><select name="agent_studio[approval]" id="agent-studio-approval">';
        foreach (ApprovalMode::cases() as $mode) {
            printf('<option value="%s"%s>%s</option>', esc_attr($mode->value), selected($config['approval'], $mode->value, false), esc_html(Labels::approval($mode)));
        }
        echo '</select><p class="description">' . esc_html__('When approval is needed, the run pauses and you get an email. Nothing happens until you approve.', 'agent-studio') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="agent_studio_run_as">' . esc_html__('Acts as', 'agent-studio') . '</label></th><td>';
        wp_dropdown_users(
            [
                'name' => 'agent_studio[run_as]',
                'id' => 'agent_studio_run_as',
                'selected' => $runAs,
                'capability' => ['edit_posts'],
            ]
        );
        echo '<p class="description">' . esc_html__('The agent has this user’s permissions, no more. Pick someone with only the access the agent needs.', 'agent-studio') . '</p></td></tr>';

        printf(
            '<tr><th scope="row"><label for="agent-studio-steps">%1$s</label></th><td><input type="number" min="1" max="50" id="agent-studio-steps" name="agent_studio[max_steps]" value="%2$d" class="small-text"><p class="description">%3$s</p></td></tr>',
            esc_html__('Step limit', 'agent-studio'),
            (int) $config['max_steps'],
            esc_html__('The most times the agent can think and act in one run.', 'agent-studio')
        );

        printf(
            '<tr><th scope="row"><label for="agent-studio-hourly">%1$s</label></th><td><input type="number" min="1" max="1000" id="agent-studio-hourly" name="agent_studio[max_runs_per_hour]" value="%2$d" class="small-text"><p class="description">%3$s</p></td></tr>',
            esc_html__('Runs per hour', 'agent-studio'),
            (int) $config['max_runs_per_hour'],
            esc_html__('Extra events beyond this are skipped. This protects your AI budget.', 'agent-studio')
        );

        echo '</tbody></table>';
    }

    public function runBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $saved = $post->post_status === 'publish';

        printf(
            '<p><button type="button" class="button button-primary" data-agent-studio-run="%1$d" %2$s>%3$s</button></p><p class="agent-studio-run-status" data-agent-studio-run-status aria-live="polite"></p>',
            (int) $post->ID,
            $saved ? '' : 'disabled',
            esc_html__('Run now', 'agent-studio')
        );

        if (! $saved) {
            echo '<p class="description">' . esc_html__('Save the agent before running it.', 'agent-studio') . '</p>';
        }

        $hasWebhook = false;
        foreach ($config['triggers'] as $trigger) {
            if ($trigger['type'] === TriggerType::Webhook->value) {
                $hasWebhook = true;
            }
        }

        if (! $hasWebhook || $config['webhook_secret'] === '') {
            echo '<p class="description">' . esc_html__('Turn on the Webhook trigger and save to get a webhook URL and secret.', 'agent-studio') . '</p>';

            return;
        }

        $url = Urls::webhook($post->ID);
        echo '<hr><p><strong>' . esc_html__('Webhook URL', 'agent-studio') . '</strong></p>';
        printf('<input type="text" readonly class="widefat" value="%s" onclick="this.select()">', esc_attr($url));
        echo '<p><strong>' . esc_html__('Secret', 'agent-studio') . '</strong></p>';
        printf('<input type="text" readonly class="widefat" id="agent-studio-secret" value="%s" onclick="this.select()">', esc_attr((string) $config['webhook_secret']));
        printf('<p><button type="button" class="button" data-agent-studio-copy="#agent-studio-secret">%s</button></p>', esc_html__('Copy secret', 'agent-studio'));
        printf('<p><label><input type="checkbox" name="agent_studio[regenerate_secret]" value="1"> %s</label></p>', esc_html__('Make a new secret when I save', 'agent-studio'));
        echo '<p class="description">' . esc_html__('Sign each request: send X-Agent-Studio-Timestamp (unix seconds) and X-Agent-Studio-Signature: sha256= followed by the HMAC-SHA256 of "timestamp.body" using the secret. Send an Idempotency-Key header to avoid duplicates.', 'agent-studio') . '</p>';
    }

    public function save(int $postId, WP_Post $post): void
    {
        $nonce = isset($_POST['agent_studio_nonce']) ? sanitize_text_field(wp_unslash($_POST['agent_studio_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'agent_studio_save_agent_' . $postId)) {
            return;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || ! current_user_can('edit_post', $postId)) {
            return;
        }

        // The mapper below validates and clamps every value, so raw input is safe to hand over.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $input = isset($_POST['agent_studio']) && is_array($_POST['agent_studio']) ? wp_unslash($_POST['agent_studio']) : [];
        $existing = AgentConfigMapper::read($postId);

        $triggers = [];
        $rawTriggers = is_array($input['triggers'] ?? null) ? $input['triggers'] : [];
        foreach ($rawTriggers as $type => $fields) {
            if (! is_array($fields) || empty($fields['on'])) {
                continue;
            }

            unset($fields['on']);
            $triggers[] = ['type' => (string) $type, 'config' => $fields];
        }

        $secret = is_string($existing['webhook_secret'] ?? null) ? $existing['webhook_secret'] : '';
        $wantsWebhook = false;
        foreach ($triggers as $trigger) {
            $wantsWebhook = $wantsWebhook || $trigger['type'] === TriggerType::Webhook->value;
        }

        if ($wantsWebhook && ($secret === '' || ! empty($input['regenerate_secret']))) {
            $secret = wp_generate_password(40, false);
        }

        $runAs = (int) ($input['run_as'] ?? 0);

        $choice = is_string($input['model_choice'] ?? null) ? $input['model_choice'] : '';
        [$provider, $model] = str_contains($choice, self::MODEL_SEPARATOR)
            ? explode(self::MODEL_SEPARATOR, $choice, 2)
            : ['', ''];

        AgentConfigMapper::write(
            $postId,
            [
                'status' => $input['status'] ?? 'paused',
                'triggers' => $triggers,
                'abilities' => $input['abilities'] ?? [],
                'skills' => $input['skills'] ?? [],
                'approval' => $input['approval'] ?? 'writes',
                'run_as' => $runAs > 0 ? $runAs : get_current_user_id(),
                'max_steps' => $input['max_steps'] ?? 10,
                'max_runs_per_hour' => $input['max_runs_per_hour'] ?? 30,
                'provider' => $provider,
                'model' => $model,
                'webhook_secret' => $secret,
            ]
        );
    }

    /**
     * @param array<string, string> $columns
     *
     * @return array<string, string>
     */
    public function columns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '',
            'title' => __('Agent', 'agent-studio'),
            'agent_studio_status' => __('Status', 'agent-studio'),
            'agent_studio_triggers' => __('Runs when', 'agent-studio'),
            'agent_studio_last_run' => __('Last run', 'agent-studio'),
            'date' => $columns['date'] ?? __('Date', 'agent-studio'),
        ];
    }

    public function column(string $column, int $postId): void
    {
        $agent = $this->agents->find($postId);
        if ($agent === null) {
            return;
        }

        if ($column === 'agent_studio_status') {
            echo $agent->isActive()
                ? '<span class="agent-studio-badge agent-studio-badge--active">' . esc_html__('Active', 'agent-studio') . '</span>'
                : '<span class="agent-studio-badge">' . esc_html__('Paused', 'agent-studio') . '</span>';
        }

        if ($column === 'agent_studio_triggers') {
            $labels = array_map(static fn ($trigger): string => Labels::trigger($trigger->type), $agent->triggers);
            echo esc_html($labels === [] ? __('Only when run by hand', 'agent-studio') : implode(', ', $labels));
        }

        if ($column === 'agent_studio_last_run') {
            $last = $this->runs->recent(1, 0, $postId)[0] ?? null;
            if ($last === null || $last->id() === null) {
                echo '&mdash;';

                return;
            }

            printf(
                '<a href="%s">%s</a>',
                esc_url(Urls::run($last->id())),
                esc_html(Labels::status($last->status()) . ' · ' . human_time_diff($last->createdAt()) . ' ' . __('ago', 'agent-studio'))
            );
        }
    }
}
