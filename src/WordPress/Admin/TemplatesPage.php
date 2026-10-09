<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\Infrastructure\AgentConfigMapper;
use AgentStudio\Infrastructure\PostTypes;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Urls;

/**
 * Ready-made agents to start from. Each one is created paused so nothing runs until you say so.
 */
final class TemplatesPage
{
    public function register(): void
    {
        add_action('admin_post_agent_studio_create_from_template', [$this, 'create']);
    }

    /**
     * @return array<string, array{title: string, summary: string, instructions: string, config: array<string, mixed>}>
     */
    public static function templates(): array
    {
        $templates = [
            'pre-publish-reviewer' => [
                'title' => __('Pre-publish reviewer', 'agent-studio'),
                'summary' => __('When a post is submitted for review, reads it and leaves editors a note with suggestions. Changes nothing else.', 'agent-studio'),
                'instructions' => __("A post has just been submitted for review.\n\nRead it with the get-post ability. Check it for clarity, spelling, grammar, a clear headline, and anything that looks unfinished (placeholder text, broken sentences, missing links).\n\nThen leave one note for the editors with the add-post-note ability. Start with a one-line verdict, then a short list of specific suggestions. Be kind and concrete. Do not change the post.", 'agent-studio'),
                'config' => [
                    'triggers' => [['type' => 'post_status', 'config' => ['post_types' => ['post'], 'to' => ['pending']]]],
                    'abilities' => ['agent-studio/get-post', 'agent-studio/add-post-note'],
                    'approval' => 'writes',
                    'max_steps' => 6,
                ],
            ],
            'welcome-new-users' => [
                'title' => __('Welcome new users', 'agent-studio'),
                'summary' => __('Writes a short, friendly welcome email to each new user. You approve every email before it goes out.', 'agent-studio'),
                'instructions' => __("Someone just registered on this site.\n\nLook them up with the get-user ability. Write a short, warm welcome email (under 120 words) in plain text. Use their display name. Tell them what they can do on the site and that they can reply with questions.\n\nSend it with the send-email ability to the email address on their account.", 'agent-studio'),
                'config' => [
                    'triggers' => [['type' => 'user_registered', 'config' => []]],
                    'abilities' => ['agent-studio/get-user', 'agent-studio/send-email'],
                    'approval' => 'writes',
                    'max_steps' => 6,
                ],
            ],
            'comment-moderator' => [
                'title' => __('Comment moderator', 'agent-studio'),
                'summary' => __('Reads each new comment and holds anything that looks like spam, abuse or an advert for moderation. It never deletes.', 'agent-studio'),
                'instructions' => __("A new comment was posted.\n\nRead it with the get-comment ability, and the post it belongs to with get-post if you need context.\n\nIf it is clearly fine, leave it as it is. If it looks like spam, abuse, or an advertisement, set its status to hold with set-comment-status so a person can review it. Never mark a comment as spam or trash it. When unsure, leave it alone.", 'agent-studio'),
                'config' => [
                    'triggers' => [['type' => 'comment_posted', 'config' => []]],
                    'abilities' => ['agent-studio/get-comment', 'agent-studio/get-post', 'agent-studio/set-comment-status'],
                    'approval' => 'destructive',
                    'max_steps' => 6,
                    'max_runs_per_hour' => 60,
                ],
            ],
        ];

        /**
         * Filters the agent templates offered on the Templates page.
         *
         * @param array<string, array<string, mixed>> $templates Templates keyed by slug.
         */
        $filtered = apply_filters('agent_studio_agent_templates', $templates);

        if (! is_array($filtered)) {
            return $templates;
        }

        // Keep only well-formed templates, so a bad filter cannot break the page.
        $valid = [];
        foreach ($filtered as $key => $template) {
            if (
                is_string($key)
                && is_array($template)
                && is_string($template['title'] ?? null)
                && is_string($template['summary'] ?? null)
                && is_string($template['instructions'] ?? null)
                && is_array($template['config'] ?? null)
            ) {
                $valid[$key] = [
                    'title' => $template['title'],
                    'summary' => $template['summary'],
                    'instructions' => $template['instructions'],
                    'config' => $template['config'],
                ];
            }
        }

        return $valid;
    }

    public function create(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to do that.', 'agent-studio'), '', ['response' => 403]);
        }

        check_admin_referer('agent_studio_create_from_template');

        $key = isset($_POST['template']) ? sanitize_key(wp_unslash($_POST['template'])) : '';
        $template = self::templates()[$key] ?? null;
        if ($template === null) {
            Flash::redirect(Urls::templates(), 'error', __('That template does not exist.', 'agent-studio'));
        }

        $postId = wp_insert_post(
            [
                'post_type' => PostTypes::AGENT,
                'post_status' => 'publish',
                'post_title' => $template['title'],
                'post_content' => wp_slash($template['instructions']),
            ],
            true
        );

        if (is_wp_error($postId)) {
            Flash::redirect(Urls::templates(), 'error', $postId->get_error_message());
        }

        AgentConfigMapper::write(
            $postId,
            array_merge($template['config'], ['status' => 'paused', 'run_as' => get_current_user_id()])
        );

        Flash::redirect(
            Urls::editAgent($postId),
            'success',
            __('Agent created and paused. Read the instructions, then set it to Active when you are ready.', 'agent-studio')
        );
    }

    public function render(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to see this page.', 'agent-studio'), '', ['response' => 403]);
        }

        echo '<div class="wrap agent-studio-wrap"><h1>' . esc_html__('Agent templates', 'agent-studio') . '</h1>';
        Flash::render();
        echo '<p>' . esc_html__('Start from a working example and change it to fit your site. Every template is created paused.', 'agent-studio') . '</p><div class="agent-studio-cards">';

        foreach (self::templates() as $key => $template) {
            echo '<div class="agent-studio-card"><h2>' . esc_html($template['title']) . '</h2><p>' . esc_html($template['summary']) . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('agent_studio_create_from_template');
            echo '<input type="hidden" name="action" value="agent_studio_create_from_template"><input type="hidden" name="template" value="' . esc_attr((string) $key) . '">';
            submit_button(__('Use this template', 'agent-studio'), 'primary', 'submit', false);
            echo '</form></div>';
        }

        echo '</div></div>';
    }
}
