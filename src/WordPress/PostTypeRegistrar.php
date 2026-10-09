<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use AgentStudio\Infrastructure\PostTypes;

/**
 * Registers the agent and knowledge content types.
 *
 * Both are private: no front end. Everything requires the manage capability,
 * which administrators have by default.
 */
final class PostTypeRegistrar
{
    private const MIGRATION_OPTION = 'agent_studio_content_version';
    private const MIGRATION_VERSION = '2';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostTypes']);
        add_filter('use_block_editor_for_post_type', [$this, 'useClassicEditor'], 10, 2);
        add_filter('wp_insert_post_data', [$this, 'keepPublished']);
    }

    public function registerPostTypes(): void
    {
        register_post_type(
            PostTypes::AGENT,
            [
                'labels' => [
                    'name' => __('Agents', 'agent-studio'),
                    'singular_name' => __('Agent', 'agent-studio'),
                    'menu_name' => __('Agents', 'agent-studio'),
                    'add_new' => __('Add New', 'agent-studio'),
                    'add_new_item' => __('Add New Agent', 'agent-studio'),
                    'edit_item' => __('Edit Agent', 'agent-studio'),
                    'new_item' => __('New Agent', 'agent-studio'),
                    'view_item' => __('View Agent', 'agent-studio'),
                    'search_items' => __('Search Agents', 'agent-studio'),
                    'not_found' => __('No agents yet.', 'agent-studio'),
                    'not_found_in_trash' => __('No agents in the Trash.', 'agent-studio'),
                    'all_items' => __('All Agents', 'agent-studio'),
                ],
                'description' => __('Automations that use AI and your site abilities.', 'agent-studio'),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => true,
                'menu_position' => 58,
                'menu_icon' => 'dashicons-superhero',
                'show_in_rest' => false,
                'supports' => ['title', 'editor'],
                'has_archive' => false,
                'rewrite' => false,
                'query_var' => false,
                'can_export' => true,
                'capabilities' => $this->capabilities(),
                // Never turn this on: with these capabilities it would make the manage capability
                // behave like a per-post capability for every check on the site.
                'map_meta_cap' => false,
            ]
        );

        register_post_type(
            PostTypes::KNOWLEDGE,
            [
                'labels' => [
                    'name' => __('Knowledge', 'agent-studio'),
                    'singular_name' => __('Knowledge item', 'agent-studio'),
                ],
                'description' => __('Skills and other things agents can know. Managed on the Agents → Skills screen.', 'agent-studio'),
                'public' => false,
                // Skills have their own screen; the stock post screens would show "Excerpt" where people expect "When to use".
                'show_ui' => false,
                'show_in_rest' => false,
                'supports' => ['title', 'editor', 'excerpt', 'revisions', 'author'],
                'taxonomies' => [PostTypes::KNOWLEDGE_TYPE],
                'has_archive' => false,
                'rewrite' => false,
                'query_var' => false,
                'can_export' => true,
                'capabilities' => $this->capabilities(),
                // Never turn this on: with these capabilities it would make the manage capability
                // behave like a per-post capability for every check on the site.
                'map_meta_cap' => false,
            ]
        );

        register_taxonomy(
            PostTypes::KNOWLEDGE_TYPE,
            [PostTypes::KNOWLEDGE],
            [
                'labels' => [
                    'name' => __('Knowledge types', 'agent-studio'),
                    'singular_name' => __('Knowledge type', 'agent-studio'),
                ],
                'public' => false,
                'show_ui' => false,
                'show_in_rest' => false,
                'hierarchical' => false,
                'rewrite' => false,
                'query_var' => false,
                'capabilities' => [
                    'manage_terms' => Capabilities::manage(),
                    'edit_terms' => Capabilities::manage(),
                    'delete_terms' => Capabilities::manage(),
                    'assign_terms' => Capabilities::manage(),
                ],
            ]
        );

        register_post_type(
            PostTypes::CONNECTION,
            [
                'labels' => [
                    'name' => __('Connections', 'agent-studio'),
                    'singular_name' => __('Connection', 'agent-studio'),
                ],
                'description' => __('Other services agents can use. Managed on the Agents → Connections screen.', 'agent-studio'),
                'public' => false,
                'show_ui' => false,
                'show_in_rest' => false,
                'supports' => ['title'],
                'has_archive' => false,
                'rewrite' => false,
                'query_var' => false,
                // Settings only; secrets live in the secret store, but there is nothing useful to export without them.
                'can_export' => false,
                'capabilities' => $this->capabilities(),
                // Never turn this on: with these capabilities it would make the manage capability
                // behave like a per-post capability for every check on the site.
                'map_meta_cap' => false,
            ]
        );

        register_post_type(
            PostTypes::CHANNEL,
            [
                'labels' => [
                    'name' => __('Channels', 'agent-studio'),
                    'singular_name' => __('Channel', 'agent-studio'),
                ],
                'description' => __('Places people talk to agents, or get notifications. Managed on the Agents → Channels screen.', 'agent-studio'),
                'public' => false,
                'show_ui' => false,
                'show_in_rest' => false,
                'supports' => ['title', 'author'],
                'has_archive' => false,
                'rewrite' => false,
                'query_var' => false,
                'can_export' => false,
                'capabilities' => $this->capabilities(),
                // Never turn this on: with these capabilities it would make the manage capability
                // behave like a per-post capability for every check on the site.
                'map_meta_cap' => false,
            ]
        );

        $this->migrateLegacySkills();
    }

    /**
     * Skills used to be their own post type. Moves them into knowledge, typed "skill".
     * Revisions follow their parent, so history is kept.
     */
    public function migrateLegacySkills(): void
    {
        if (get_option(self::MIGRATION_OPTION) === self::MIGRATION_VERSION) {
            return;
        }

        $ids = get_posts(
            [
                'post_type' => PostTypes::LEGACY_SKILL,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
                'suppress_filters' => true,
            ]
        );

        global $wpdb;

        foreach ($ids as $id) {
            // Like set_post_type(), without touching anything else on the post.
            $wpdb->update($wpdb->posts, ['post_type' => PostTypes::KNOWLEDGE], ['ID' => (int) $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            clean_post_cache((int) $id);
            wp_set_object_terms((int) $id, PostTypes::TYPE_SKILL, PostTypes::KNOWLEDGE_TYPE);
        }

        update_option(self::MIGRATION_OPTION, self::MIGRATION_VERSION, false);
    }

    /**
     * Agent instructions are plain text, so the classic editor is the right tool.
     */
    public function useClassicEditor(bool $useBlockEditor, string $postType): bool
    {
        return $postType === PostTypes::AGENT ? false : $useBlockEditor;
    }

    /**
     * Agents and knowledge have no draft workflow. Saving them saves them.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function keepPublished(array $data): array
    {
        $isOurs = in_array($data['post_type'] ?? '', [PostTypes::AGENT, PostTypes::KNOWLEDGE], true);
        if ($isOurs && in_array($data['post_status'] ?? '', ['draft', 'pending'], true)) {
            $data['post_status'] = 'publish';
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function capabilities(): array
    {
        $capability = Capabilities::manage();

        return [
            'edit_post' => $capability,
            'read_post' => $capability,
            'delete_post' => $capability,
            'edit_posts' => $capability,
            'edit_others_posts' => $capability,
            'delete_posts' => $capability,
            'publish_posts' => $capability,
            'read_private_posts' => $capability,
            'delete_private_posts' => $capability,
            'delete_published_posts' => $capability,
            'delete_others_posts' => $capability,
            'edit_private_posts' => $capability,
            'edit_published_posts' => $capability,
            'create_posts' => $capability,
        ];
    }
}
