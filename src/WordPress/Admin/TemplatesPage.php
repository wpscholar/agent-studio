<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Admin;

use WPAgents\Infrastructure\AgentConfigMapper;
use WPAgents\Infrastructure\PostTypes;
use WPAgents\WordPress\Capabilities;
use WPAgents\WordPress\Urls;

/**
 * Ready-made agents to start from. Each one is created paused so nothing runs until you say so.
 */
final class TemplatesPage
{
    public function register(): void
    {
        add_action('admin_post_wpa_create_from_template', [$this, 'create']);
    }

    /**
     * @return array<string, array{title: string, summary: string, instructions: string, config: array<string, mixed>}>
     */
    public static function templates(): array
    {
        $templates = [
            'pre-publish-reviewer' => [
                'title' => __('Pre-publish reviewer', 'wp-agents'),
                'summary' => __('When a post is submitted for review, reads it and leaves editors a note with suggestions. Changes nothing else.', 'wp-agents'),
                'instructions' => __("A post has just been submitted for review.\n\nRead it with the get-post ability. Check it for clarity, spelling, grammar, a clear headline, and anything that looks unfinished (placeholder text, broken sentences, missing links).\n\nThen leave one note for the editors with the add-post-note ability. Start with a one-line verdict, then a short list of specific suggestions. Be kind and concrete. Do not change the post.", 'wp-agents'),
                'config' => [
                    'triggers' => [['type' => 'post_status', 'config' => ['post_types' => ['post'], 'to' => ['pending']]]],
                    'abilities' => ['wp-agents/get-post', 'wp-agents/add-post-note'],
                    'approval' => 'writes',
                    'max_steps' => 6,
                ],
            ],
            'welcome-new-users' => [
                'title' => __('Welcome new users', 'wp-agents'),
                'summary' => __('Writes a short, friendly welcome email to each new user. You approve every email before it goes out.', 'wp-agents'),
                'instructions' => __("Someone just registered on this site.\n\nLook them up with the get-user ability. Write a short, warm welcome email (under 120 words) in plain text. Use their display name. Tell them what they can do on the site and that they can reply with questions.\n\nSend it with the send-email ability to the email address on their account.", 'wp-agents'),
                'config' => [
                    'triggers' => [['type' => 'user_registered', 'config' => []]],
                    'abilities' => ['wp-agents/get-user', 'wp-agents/send-email'],
                    'approval' => 'writes',
                    'max_steps' => 6,
                ],
            ],
            'comment-moderator' => [
                'title' => __('Comment moderator', 'wp-agents'),
                'summary' => __('Reads each new comment and holds anything that looks like spam, abuse or an advert for moderation. It never deletes.', 'wp-agents'),
                'instructions' => __("A new comment was posted.\n\nRead it with the get-comment ability, and the post it belongs to with get-post if you need context.\n\nIf it is clearly fine, leave it as it is. If it looks like spam, abuse, or an advertisement, set its status to hold with set-comment-status so a person can review it. Never mark a comment as spam or trash it. When unsure, leave it alone.", 'wp-agents'),
                'config' => [
                    'triggers' => [['type' => 'comment_posted', 'config' => []]],
                    'abilities' => ['wp-agents/get-comment', 'wp-agents/get-post', 'wp-agents/set-comment-status'],
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
        $filtered = apply_filters('wpa_agent_templates', $templates);

        return is_array($filtered) ? $filtered : $templates;
    }

    public function create(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to do that.', 'wp-agents'), '', ['response' => 403]);
        }

        check_admin_referer('wpa_create_from_template');

        $key = isset($_POST['template']) ? sanitize_key(wp_unslash($_POST['template'])) : '';
        $template = self::templates()[$key] ?? null;
        if ($template === null) {
            Flash::redirect(Urls::templates(), 'error', __('That template does not exist.', 'wp-agents'));
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
            __('Agent created and paused. Read the instructions, then set it to Active when you are ready.', 'wp-agents')
        );
    }

    public function render(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to see this page.', 'wp-agents'), '', ['response' => 403]);
        }

        echo '<div class="wrap wpa-wrap"><h1>' . esc_html__('Agent templates', 'wp-agents') . '</h1>';
        Flash::render();
        echo '<p>' . esc_html__('Start from a working example and change it to fit your site. Every template is created paused.', 'wp-agents') . '</p><div class="wpa-cards">';

        foreach (self::templates() as $key => $template) {
            echo '<div class="wpa-card"><h2>' . esc_html($template['title']) . '</h2><p>' . esc_html($template['summary']) . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('wpa_create_from_template');
            echo '<input type="hidden" name="action" value="wpa_create_from_template"><input type="hidden" name="template" value="' . esc_attr((string) $key) . '">';
            submit_button(__('Use this template', 'wp-agents'), 'primary', 'submit', false);
            echo '</form></div>';
        }

        echo '</div></div>';
    }
}
