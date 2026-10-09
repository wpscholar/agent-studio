<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Abilities;

use WP_Error;
use WP_Post;
use AgentStudio\Infrastructure\PostTypes;

/**
 * A small set of content abilities so agents are useful on a plain WordPress site.
 *
 * They follow the normal rules: each one checks the capabilities of the user
 * the agent runs as. Other plugins register richer abilities the same way,
 * and agents can use those too. Return false from the
 * agent_studio_register_starter_abilities filter to turn these off.
 */
final class StarterAbilities
{
    private const MAX_NOTES = 20;

    public function register(): void
    {
        add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
    }

    public function registerAbilities(): void
    {
        /**
         * Filters whether Agent Studio registers its starter abilities.
         *
         * @param bool $register Default true.
         */
        if (! function_exists('wp_register_ability') || ! apply_filters('agent_studio_register_starter_abilities', true)) {
            return;
        }

        $meta = static function (bool $readonly, bool $idempotent, string $risk = ''): array {
            $meta = [
                'annotations' => ['readonly' => $readonly, 'destructive' => false, 'idempotent' => $idempotent],
                'show_in_rest' => true,
                'mcp' => ['public' => (bool) apply_filters('agent_studio_expose_abilities_to_mcp', false)],
            ];

            if ($risk !== '') {
                $meta['agent_studio'] = ['risk' => $risk];
            }

            return $meta;
        };

        wp_register_ability(
            'agent-studio/get-post',
            [
                'label' => __('Get a post', 'agent-studio'),
                'description' => __('Returns a post or page: title, content, excerpt, status, author, dates and terms.', 'agent-studio'),
                'category' => AgentAbilities::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer', 'description' => 'The post ID.']],
                    'required' => ['id'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'type' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'excerpt' => ['type' => 'string'],
                        'author_id' => ['type' => 'integer'],
                        'author_name' => ['type' => 'string'],
                        'date_gmt' => ['type' => 'string'],
                        'modified_gmt' => ['type' => 'string'],
                        'permalink' => ['type' => 'string'],
                        'terms' => ['type' => 'object'],
                    ],
                ],
                'execute_callback' => [$this, 'getPost'],
                'permission_callback' => static fn ($input): bool => is_array($input) && current_user_can('read_post', (int) ($input['id'] ?? 0)),
                'meta' => $meta(true, true),
            ]
        );

        wp_register_ability(
            'agent-studio/search-posts',
            [
                'label' => __('Search posts', 'agent-studio'),
                'description' => __('Finds posts by keyword, post type and status. Returns IDs, titles and statuses.', 'agent-studio'),
                'category' => AgentAbilities::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string', 'description' => 'Keywords to look for.'],
                        'post_type' => ['type' => 'string', 'description' => 'Post type slug. Default "post".'],
                        'status' => ['type' => 'string', 'description' => 'Post status or "any". Default "any".'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'How many results. Default 10.'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'posts' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer'],
                                    'title' => ['type' => 'string'],
                                    'status' => ['type' => 'string'],
                                    'type' => ['type' => 'string'],
                                    'date_gmt' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'execute_callback' => [$this, 'searchPosts'],
                'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
                'meta' => $meta(true, true),
            ]
        );

        wp_register_ability(
            'agent-studio/update-post',
            [
                'label' => __('Update a post', 'agent-studio'),
                'description' => __('Changes the title, content, excerpt or status of a post. Only the fields you send are changed.', 'agent-studio'),
                'category' => AgentAbilities::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'The post ID.'],
                        'title' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'excerpt' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['draft', 'pending', 'private', 'publish']],
                    ],
                    'required' => ['id'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'status' => ['type' => 'string'],
                        'permalink' => ['type' => 'string'],
                    ],
                ],
                'execute_callback' => [$this, 'updatePost'],
                'permission_callback' => static fn ($input): bool => is_array($input) && current_user_can('edit_post', (int) ($input['id'] ?? 0)),
                'meta' => $meta(false, true),
            ]
        );

        wp_register_ability(
            'agent-studio/add-post-note',
            [
                'label' => __('Add a note to a post', 'agent-studio'),
                'description' => __('Leaves a private note for editors on a post. Notes are shown in the post editor and never on the site.', 'agent-studio'),
                'category' => AgentAbilities::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'post_id' => ['type' => 'integer'],
                        'note' => ['type' => 'string', 'maxLength' => 2000],
                    ],
                    'required' => ['post_id', 'note'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'post_id' => ['type' => 'integer'],
                        'notes' => ['type' => 'integer', 'description' => 'How many notes the post has now.'],
                    ],
                ],
                'execute_callback' => [$this, 'addPostNote'],
                'permission_callback' => static fn ($input): bool => is_array($input) && current_user_can('edit_post', (int) ($input['post_id'] ?? 0)),
                'meta' => $meta(false, false),
            ]
        );

        wp_register_ability(
            'agent-studio/get-comment',
            [
                'label' => __('Get a comment', 'agent-studio'),
                'description' => __('Returns a comment: text, author, status and the post it belongs to.', 'agent-studio'),
                'category' => AgentAbilities::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'post_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string'],
                        'author_name' => ['type' => 'string'],
                        'author_email' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'date_gmt' => ['type' => 'string'],
                    ],
                ],
                'execute_callback' => [$this, 'getComment'],
                'permission_callback' => static fn ($input): bool => is_array($input) && current_user_can('edit_comment', (int) ($input['id'] ?? 0)),
                'meta' => $meta(true, true),
            ]
        );

        wp_register_ability(
            'agent-studio/set-comment-status',
            [
                'label' => __('Set a comment status', 'agent-studio'),
                'description' => __('Approves, holds for moderation, marks as spam, or trashes a comment.', 'agent-studio'),
                'category' => AgentAbilities::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'enum' => ['approve', 'hold', 'spam', 'trash']],
                    ],
                    'required' => ['id', 'status'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'status' => ['type' => 'string'],
                    ],
                ],
                'execute_callback' => [$this, 'setCommentStatus'],
                'permission_callback' => static fn ($input): bool => is_array($input) && current_user_can('edit_comment', (int) ($input['id'] ?? 0)),
                'meta' => $meta(false, true),
            ]
        );

        wp_register_ability(
            'agent-studio/get-user',
            [
                'label' => __('Get a user', 'agent-studio'),
                'description' => __('Returns a user: login, display name, roles, registration date, and email when you may see it.', 'agent-studio'),
                'category' => AgentAbilities::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'login' => ['type' => 'string'],
                        'display_name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'roles' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'registered' => ['type' => 'string'],
                    ],
                ],
                'execute_callback' => [$this, 'getUser'],
                'permission_callback' => static fn (): bool => current_user_can('list_users'),
                'meta' => $meta(true, true),
            ]
        );

        wp_register_ability(
            'agent-studio/send-email',
            [
                'label' => __('Send an email', 'agent-studio'),
                'description' => __('Sends a plain-text email from this site to one recipient.', 'agent-studio'),
                'category' => AgentAbilities::CATEGORY,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'to' => ['type' => 'string', 'description' => 'The recipient email address.'],
                        'subject' => ['type' => 'string', 'maxLength' => 200],
                        'body' => ['type' => 'string', 'maxLength' => 10000, 'description' => 'Plain text.'],
                    ],
                    'required' => ['to', 'subject', 'body'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => ['sent' => ['type' => 'boolean']],
                ],
                'execute_callback' => [$this, 'sendEmail'],
                'permission_callback' => static fn (): bool => current_user_can('manage_options'),
                'meta' => $meta(false, false, 'external'),
            ]
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function getPost(array $input)
    {
        $post = get_post((int) ($input['id'] ?? 0));
        if (! $post instanceof WP_Post) {
            return new WP_Error('agent_studio_not_found', __('Post not found.', 'agent-studio'));
        }

        $terms = [];
        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            $names = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'names']);
            if (! is_wp_error($names) && $names !== []) {
                $terms[$taxonomy] = array_values($names);
            }
        }

        return [
            'id' => $post->ID,
            'type' => $post->post_type,
            'status' => $post->post_status,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'author_id' => (int) $post->post_author,
            'author_name' => (string) get_the_author_meta('display_name', (int) $post->post_author),
            'date_gmt' => $post->post_date_gmt,
            'modified_gmt' => $post->post_modified_gmt,
            'permalink' => (string) get_permalink($post),
            'terms' => (object) $terms,
        ];
    }

    /**
     * @param array<string, mixed>|null $input
     *
     * @return array<string, mixed>
     */
    public function searchPosts(?array $input = null): array
    {
        $input = $input ?? [];
        $postType = is_string($input['post_type'] ?? null) && post_type_exists($input['post_type']) ? $input['post_type'] : 'post';
        $limit = max(1, min(50, (int) ($input['limit'] ?? 10)));
        $status = is_string($input['status'] ?? null) && $input['status'] !== '' ? $input['status'] : 'any';

        $posts = get_posts(
            [
                'post_type' => $postType,
                'post_status' => $status,
                's' => is_string($input['search'] ?? null) ? $input['search'] : '',
                'numberposts' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
            ]
        );

        $results = [];
        foreach ($posts as $post) {
            if ($post instanceof WP_Post && current_user_can('read_post', $post->ID)) {
                $results[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'date_gmt' => $post->post_date_gmt,
                ];
            }
        }

        return ['posts' => $results];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function updatePost(array $input)
    {
        $id = (int) ($input['id'] ?? 0);
        $post = get_post($id);
        if (! $post instanceof WP_Post) {
            return new WP_Error('agent_studio_not_found', __('Post not found.', 'agent-studio'));
        }

        $status = is_string($input['status'] ?? null) ? $input['status'] : '';
        if ($status === 'publish' && ! current_user_can('publish_post', $id)) {
            return new WP_Error('agent_studio_forbidden', __('The agent is not allowed to publish this post.', 'agent-studio'));
        }

        /**
         * @var array{
         *     ID: int, post_title?: string, post_content?: string, post_excerpt?: string, post_status?: string
         * } $data
         */
        $data = ['ID' => $id];
        foreach (['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt'] as $key => $column) {
            if (is_string($input[$key] ?? null)) {
                $data[$column] = wp_slash($input[$key]);
            }
        }

        if ($status !== '') {
            $data['post_status'] = $status;
        }

        $result = wp_update_post($data, true);
        if (is_wp_error($result)) {
            return $result;
        }

        $updated = get_post($id);

        return [
            'id' => $id,
            'status' => $updated instanceof WP_Post ? $updated->post_status : '',
            'permalink' => (string) get_permalink($id),
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function addPostNote(array $input)
    {
        $postId = (int) ($input['post_id'] ?? 0);
        if (! get_post($postId) instanceof WP_Post) {
            return new WP_Error('agent_studio_not_found', __('Post not found.', 'agent-studio'));
        }

        $text = sanitize_textarea_field(is_string($input['note'] ?? null) ? $input['note'] : '');
        if ($text === '') {
            return new WP_Error('agent_studio_empty_note', __('The note is empty.', 'agent-studio'));
        }

        $user = wp_get_current_user();
        $notes = get_post_meta($postId, PostTypes::NOTES_META, true);
        $notes = is_array($notes) ? $notes : [];
        $notes[] = [
            'at' => time(),
            'author' => $user->display_name,
            'note' => mb_substr($text, 0, 2000),
        ];
        $notes = array_slice($notes, -self::MAX_NOTES);

        update_post_meta($postId, PostTypes::NOTES_META, $notes);

        return ['post_id' => $postId, 'notes' => count($notes)];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function getComment(array $input)
    {
        $comment = get_comment((int) ($input['id'] ?? 0));
        if (! $comment instanceof \WP_Comment) {
            return new WP_Error('agent_studio_not_found', __('Comment not found.', 'agent-studio'));
        }

        return [
            'id' => (int) $comment->comment_ID,
            'post_id' => (int) $comment->comment_post_ID,
            'status' => (string) wp_get_comment_status($comment),
            'author_name' => $comment->comment_author,
            'author_email' => $comment->comment_author_email,
            'content' => $comment->comment_content,
            'date_gmt' => $comment->comment_date_gmt,
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function setCommentStatus(array $input)
    {
        $id = (int) ($input['id'] ?? 0);
        $status = is_string($input['status'] ?? null) ? $input['status'] : '';

        if (! in_array($status, ['approve', 'hold', 'spam', 'trash'], true)) {
            return new WP_Error('agent_studio_invalid_status', __('Status must be approve, hold, spam or trash.', 'agent-studio'));
        }

        if (! get_comment($id) instanceof \WP_Comment) {
            return new WP_Error('agent_studio_not_found', __('Comment not found.', 'agent-studio'));
        }

        if (! wp_set_comment_status($id, $status)) {
            return new WP_Error('agent_studio_update_failed', __('The comment status could not be changed.', 'agent-studio'));
        }

        return ['id' => $id, 'status' => $status];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function getUser(array $input)
    {
        $id = (int) ($input['id'] ?? 0);
        $user = get_userdata($id);
        if ($user === false) {
            return new WP_Error('agent_studio_not_found', __('User not found.', 'agent-studio'));
        }

        return [
            'id' => $user->ID,
            'login' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => current_user_can('edit_user', $id) ? $user->user_email : '',
            'roles' => array_values($user->roles),
            'registered' => $user->user_registered,
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|WP_Error
     */
    public function sendEmail(array $input)
    {
        $to = is_string($input['to'] ?? null) ? trim($input['to']) : '';
        if (! is_email($to)) {
            return new WP_Error('agent_studio_invalid_email', __('The recipient is not a valid email address.', 'agent-studio'));
        }

        /**
         * Filters whether agents may email an address. Return false to block it.
         *
         * @param bool   $allowed Default true.
         * @param string $to      The recipient.
         */
        if (! apply_filters('agent_studio_allow_email_recipient', true, $to)) {
            return new WP_Error('agent_studio_recipient_blocked', __('This recipient is not allowed.', 'agent-studio'));
        }

        $subject = sanitize_text_field(is_string($input['subject'] ?? null) ? $input['subject'] : '');
        $body = sanitize_textarea_field(is_string($input['body'] ?? null) ? $input['body'] : '');

        if ($subject === '' || $body === '') {
            return new WP_Error('agent_studio_empty_email', __('The subject and body are required.', 'agent-studio'));
        }

        return ['sent' => wp_mail($to, $subject, $body)];
    }
}
