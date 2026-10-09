<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

/**
 * Ready-made agents to start from, offered when you add an agent. Each one is
 * created as a draft so nothing runs until someone reads it and switches it on.
 */
final class AgentTemplates
{
    /**
     * @return array<string, array{title: string, summary: string, instructions: string, config: array<string, mixed>}>
     */
    public static function all(): array
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
         * Filters the agent templates offered when someone adds an agent.
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
}
