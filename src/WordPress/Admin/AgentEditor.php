<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Admin;

use WP_Post;
use WPAgents\Contracts\AbilityGateway;
use WPAgents\Contracts\AgentRepository;
use WPAgents\Contracts\RunRepository;
use WPAgents\Contracts\SkillRepository;
use WPAgents\Domain\Agent\AgentStatus;
use WPAgents\Domain\Agent\ApprovalMode;
use WPAgents\Domain\Agent\TriggerType;
use WPAgents\Domain\Run\RunStatus;
use WPAgents\Infrastructure\AgentConfigMapper;
use WPAgents\Infrastructure\PostTypes;
use WPAgents\WordPress\Labels;
use WPAgents\WordPress\Urls;

/**
 * The agent edit screen: instructions in the editor, everything else in boxes.
 */
final class AgentEditor
{
    public function __construct(
        private readonly AgentRepository $agents,
        private readonly RunRepository $runs,
        private readonly SkillRepository $skills,
        private readonly AbilityGateway $abilities,
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
        return $post->post_type === PostTypes::AGENT ? __('Agent name', 'wp-agents') : $text;
    }

    public function instructionsHint(WP_Post $post): void
    {
        if ($post->post_type !== PostTypes::AGENT) {
            return;
        }

        echo '<p class="wpa-hint"><strong>' . esc_html__('Instructions', 'wp-agents') . '</strong> ';
        echo esc_html__('Tell the agent what to do, in plain language. It receives the details of the event that started it.', 'wp-agents') . '</p>';
    }

    public function addMetaBoxes(): void
    {
        add_meta_box('wpa-triggers', __('When should this agent run?', 'wp-agents'), [$this, 'triggersBox'], PostTypes::AGENT, 'normal', 'high');
        add_meta_box('wpa-abilities', __('What can it do?', 'wp-agents'), [$this, 'abilitiesBox'], PostTypes::AGENT, 'normal', 'default');
        add_meta_box('wpa-skills', __('Skills', 'wp-agents'), [$this, 'skillsBox'], PostTypes::AGENT, 'normal', 'default');
        add_meta_box('wpa-safety', __('Safety and limits', 'wp-agents'), [$this, 'safetyBox'], PostTypes::AGENT, 'normal', 'default');
        add_meta_box('wpa-status', __('Status', 'wp-agents'), [$this, 'statusBox'], PostTypes::AGENT, 'side', 'high');
        add_meta_box('wpa-run', __('Run and webhook', 'wp-agents'), [$this, 'runBox'], PostTypes::AGENT, 'side', 'default');
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
        wp_nonce_field('wpa_save_agent_' . $post->ID, 'wpa_nonce');
        $config = $this->config($post);

        echo '<p><label class="screen-reader-text" for="wpa-status-field">' . esc_html__('Status', 'wp-agents') . '</label>';
        echo '<select name="wpa[status]" id="wpa-status-field" class="widefat">';
        foreach ([AgentStatus::Paused->value => __('Paused', 'wp-agents'), AgentStatus::Active->value => __('Active', 'wp-agents')] as $value => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($config['status'], $value, false), esc_html($label));
        }
        echo '</select></p>';
        echo '<p class="description">' . esc_html__('A paused agent ignores events. You can still run it by hand.', 'wp-agents') . '</p>';
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
                'help' => __('When a post changes status, for example when it is submitted for review or published.', 'wp-agents'),
                'fields' => [
                    'post_types' => [__('Post types', 'wp-agents'), 'post'],
                    'from' => [__('From status', 'wp-agents'), __('any', 'wp-agents')],
                    'to' => [__('To status', 'wp-agents'), 'pending, publish'],
                ],
            ],
            TriggerType::UserRegistered->value => [
                'help' => __('When someone registers or is added as a user.', 'wp-agents'),
                'fields' => ['roles' => [__('Roles', 'wp-agents'), __('any', 'wp-agents')]],
            ],
            TriggerType::CommentPosted->value => [
                'help' => __('When a new comment is left.', 'wp-agents'),
                'fields' => ['post_types' => [__('Post types', 'wp-agents'), __('any', 'wp-agents')]],
            ],
            TriggerType::Webhook->value => [
                'help' => __('When another system calls this agent’s secure webhook URL.', 'wp-agents'),
                'fields' => [],
            ],
        ];

        echo '<p class="description">' . esc_html__('Choose what starts this agent. Leave a filter empty to match everything. Separate several values with commas.', 'wp-agents') . '</p>';
        echo '<div class="wpa-triggers">';

        foreach ($rows as $type => $row) {
            $enabled = isset($byType[$type]);
            $label = Labels::trigger(TriggerType::from($type));
            echo '<div class="wpa-trigger' . ($enabled ? ' is-on' : '') . '">';
            printf(
                '<label class="wpa-trigger__title"><input type="checkbox" name="wpa[triggers][%1$s][on]" value="1" data-wpa-trigger %2$s> <strong>%3$s</strong></label>',
                esc_attr($type),
                checked($enabled, true, false),
                esc_html($label)
            );
            echo '<p class="description">' . esc_html($row['help']) . '</p>';

            if ($row['fields'] !== []) {
                echo '<div class="wpa-trigger__fields">';
                foreach ($row['fields'] as $key => [$fieldLabel, $placeholder]) {
                    $value = implode(', ', array_map('strval', (array) ($byType[$type][$key] ?? [])));
                    printf(
                        '<label>%1$s <input type="text" class="regular-text" name="wpa[triggers][%2$s][%3$s]" value="%4$s" placeholder="%5$s"></label>',
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

    public function abilitiesBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $selected = $config['abilities'];
        $available = $this->abilities->available();

        echo '<p class="description">' . esc_html__('The agent can only use what you tick here. Abilities come from WordPress and from other plugins.', 'wp-agents') . '</p>';

        if ($available === []) {
            echo '<p>' . esc_html__('No abilities are registered on this site yet.', 'wp-agents') . '</p>';
        } else {
            echo '<p><input type="search" class="regular-text" data-wpa-filter placeholder="' . esc_attr__('Filter abilities…', 'wp-agents') . '"></p>';
        }

        $known = [];
        echo '<div class="wpa-abilities">';
        foreach ($available as $info) {
            $known[$info->name] = true;
            printf(
                '<label class="wpa-ability" data-wpa-text="%1$s"><input type="checkbox" name="wpa[abilities][]" value="%2$s" %3$s> <span class="wpa-ability__name">%4$s</span> <span class="wpa-risk wpa-risk--%5$s" title="%6$s">%7$s</span><br><span class="description"><code>%2$s</code> %8$s</span></label>',
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
                    '<label class="wpa-ability wpa-ability--missing"><input type="checkbox" name="wpa[abilities][]" value="%1$s" checked> <code>%1$s</code> <span class="description">%2$s</span></label>',
                    esc_attr($name),
                    esc_html__('Not registered right now. Untick it to remove.', 'wp-agents')
                );
            }
        }
    }

    public function skillsBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $skills = $this->skills->all();

        echo '<p class="description">' . esc_html__('Skills are reusable instructions. The agent sees each skill’s description and loads the full text only when it needs it.', 'wp-agents') . '</p>';

        if ($skills === []) {
            printf(
                '<p>%s <a href="%s">%s</a> · <a href="%s">%s</a></p>',
                esc_html__('No skills yet.', 'wp-agents'),
                esc_url(admin_url('post-new.php?post_type=' . PostTypes::SKILL)),
                esc_html__('Add a skill', 'wp-agents'),
                esc_url(Urls::importSkill()),
                esc_html__('Import a SKILL.md', 'wp-agents')
            );

            return;
        }

        foreach ($skills as $skill) {
            printf(
                '<label class="wpa-skill"><input type="checkbox" name="wpa[skills][]" value="%1$s" %2$s> <strong>%3$s</strong><br><span class="description">%4$s</span></label>',
                esc_attr($skill->slug),
                checked(in_array($skill->slug, $config['skills'], true), true, false),
                esc_html($skill->name),
                esc_html($skill->description)
            );
        }
    }

    public function safetyBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $runAs = (int) $config['run_as'] > 0 ? (int) $config['run_as'] : get_current_user_id();

        echo '<table class="form-table wpa-form"><tbody>';

        echo '<tr><th scope="row"><label for="wpa-approval">' . esc_html__('Approval', 'wp-agents') . '</label></th><td><select name="wpa[approval]" id="wpa-approval">';
        foreach (ApprovalMode::cases() as $mode) {
            printf('<option value="%s"%s>%s</option>', esc_attr($mode->value), selected($config['approval'], $mode->value, false), esc_html(Labels::approval($mode)));
        }
        echo '</select><p class="description">' . esc_html__('When approval is needed, the run pauses and you get an email. Nothing happens until you approve.', 'wp-agents') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="wpa_run_as">' . esc_html__('Acts as', 'wp-agents') . '</label></th><td>';
        wp_dropdown_users(
            [
                'name' => 'wpa[run_as]',
                'id' => 'wpa_run_as',
                'selected' => $runAs,
                'capability' => ['edit_posts'],
            ]
        );
        echo '<p class="description">' . esc_html__('The agent has this user’s permissions, no more. Pick someone with only the access the agent needs.', 'wp-agents') . '</p></td></tr>';

        printf(
            '<tr><th scope="row"><label for="wpa-steps">%1$s</label></th><td><input type="number" min="1" max="50" id="wpa-steps" name="wpa[max_steps]" value="%2$d" class="small-text"><p class="description">%3$s</p></td></tr>',
            esc_html__('Step limit', 'wp-agents'),
            (int) $config['max_steps'],
            esc_html__('The most times the agent can think and act in one run.', 'wp-agents')
        );

        printf(
            '<tr><th scope="row"><label for="wpa-hourly">%1$s</label></th><td><input type="number" min="1" max="1000" id="wpa-hourly" name="wpa[max_runs_per_hour]" value="%2$d" class="small-text"><p class="description">%3$s</p></td></tr>',
            esc_html__('Runs per hour', 'wp-agents'),
            (int) $config['max_runs_per_hour'],
            esc_html__('Extra events beyond this are skipped. This protects your AI budget.', 'wp-agents')
        );

        printf(
            '<tr><th scope="row"><label for="wpa-model">%1$s</label></th><td><input type="text" id="wpa-model" name="wpa[model]" value="%2$s" class="regular-text" placeholder="%3$s"><p class="description">%4$s</p></td></tr>',
            esc_html__('Model', 'wp-agents'),
            esc_attr((string) $config['model']),
            esc_attr__('Use the site default', 'wp-agents'),
            esc_html__('Optional. Leave empty to use the model chosen in your AI settings.', 'wp-agents')
        );

        echo '</tbody></table>';
    }

    public function runBox(WP_Post $post): void
    {
        $config = $this->config($post);
        $saved = $post->post_status === 'publish';

        printf(
            '<p><button type="button" class="button button-primary" data-wpa-run="%1$d" %2$s>%3$s</button></p><p class="wpa-run-status" data-wpa-run-status aria-live="polite"></p>',
            (int) $post->ID,
            $saved ? '' : 'disabled',
            esc_html__('Run now', 'wp-agents')
        );

        if (! $saved) {
            echo '<p class="description">' . esc_html__('Save the agent before running it.', 'wp-agents') . '</p>';
        }

        $hasWebhook = false;
        foreach ($config['triggers'] as $trigger) {
            if ($trigger['type'] === TriggerType::Webhook->value) {
                $hasWebhook = true;
            }
        }

        if (! $hasWebhook || $config['webhook_secret'] === '') {
            echo '<p class="description">' . esc_html__('Turn on the Webhook trigger and save to get a webhook URL and secret.', 'wp-agents') . '</p>';

            return;
        }

        $url = Urls::webhook($post->ID);
        echo '<hr><p><strong>' . esc_html__('Webhook URL', 'wp-agents') . '</strong></p>';
        printf('<input type="text" readonly class="widefat" value="%s" onclick="this.select()">', esc_attr($url));
        echo '<p><strong>' . esc_html__('Secret', 'wp-agents') . '</strong></p>';
        printf('<input type="text" readonly class="widefat" id="wpa-secret" value="%s" onclick="this.select()">', esc_attr((string) $config['webhook_secret']));
        printf('<p><button type="button" class="button" data-wpa-copy="#wpa-secret">%s</button></p>', esc_html__('Copy secret', 'wp-agents'));
        printf('<p><label><input type="checkbox" name="wpa[regenerate_secret]" value="1"> %s</label></p>', esc_html__('Make a new secret when I save', 'wp-agents'));
        echo '<p class="description">' . esc_html__('Sign each request: send X-WPA-Timestamp (unix seconds) and X-WPA-Signature: sha256= followed by the HMAC-SHA256 of "timestamp.body" using the secret. Send an Idempotency-Key header to avoid duplicates.', 'wp-agents') . '</p>';
    }

    public function save(int $postId, WP_Post $post): void
    {
        $nonce = isset($_POST['wpa_nonce']) ? sanitize_text_field(wp_unslash($_POST['wpa_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'wpa_save_agent_' . $postId)) {
            return;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || ! current_user_can('edit_post', $postId)) {
            return;
        }

        // The mapper below validates and clamps every value, so raw input is safe to hand over.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $input = isset($_POST['wpa']) && is_array($_POST['wpa']) ? wp_unslash($_POST['wpa']) : [];
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
                'model' => $input['model'] ?? '',
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
            'title' => __('Agent', 'wp-agents'),
            'wpa_status' => __('Status', 'wp-agents'),
            'wpa_triggers' => __('Runs when', 'wp-agents'),
            'wpa_last_run' => __('Last run', 'wp-agents'),
            'date' => $columns['date'] ?? __('Date', 'wp-agents'),
        ];
    }

    public function column(string $column, int $postId): void
    {
        $agent = $this->agents->find($postId);
        if ($agent === null) {
            return;
        }

        if ($column === 'wpa_status') {
            echo $agent->isActive()
                ? '<span class="wpa-badge wpa-badge--active">' . esc_html__('Active', 'wp-agents') . '</span>'
                : '<span class="wpa-badge">' . esc_html__('Paused', 'wp-agents') . '</span>';
        }

        if ($column === 'wpa_triggers') {
            $labels = array_map(static fn ($trigger): string => Labels::trigger($trigger->type), $agent->triggers);
            echo esc_html($labels === [] ? __('Only when run by hand', 'wp-agents') : implode(', ', $labels));
        }

        if ($column === 'wpa_last_run') {
            $last = $this->runs->recent(1, 0, $postId)[0] ?? null;
            if ($last === null || $last->id() === null) {
                echo '&mdash;';

                return;
            }

            printf(
                '<a href="%s">%s</a>',
                esc_url(Urls::run($last->id())),
                esc_html(Labels::status($last->status()) . ' · ' . human_time_diff($last->createdAt()) . ' ' . __('ago', 'wp-agents'))
            );
        }
    }
}
