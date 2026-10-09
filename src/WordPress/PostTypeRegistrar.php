<?php

declare(strict_types=1);

namespace AgentStudio\WordPress;

use AgentStudio\Infrastructure\AgentConfigMapper;
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

    /** 2: skills became knowledge. 3: an agent's status became its post status. */
    private const MIGRATION_VERSION = 3;

    public function register(): void
    {
        add_action('init', [$this, 'registerPostTypes']);
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
                // The Agents screen (see Admin\Menu) replaces the stock list and editor. show_ui stays on so
                // old links to them still load far enough to be redirected there.
                'show_ui' => true,
                'show_in_menu' => false,
                'show_in_rest' => false,
                'supports' => ['title', 'editor', 'author'],
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

        register_post_status(
            PostTypes::STATUS_PAUSED,
            [
                'label' => _x('Paused', 'agent status', 'agent-studio'),
                'public' => false,
                'internal' => false,
                'protected' => true,
                'exclude_from_search' => true,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => false,
                /* translators: %s: number of agents */
                'label_count' => _n_noop('Paused <span class="count">(%s)</span>', 'Paused <span class="count">(%s)</span>', 'agent-studio'),
            ]
        );

        $this->migrate();
    }

    /**
     * Brings stored content up to date with this version of the plugin, once.
     */
    public function migrate(): void
    {
        $version = (int) get_option(self::MIGRATION_OPTION, 0);
        if ($version >= self::MIGRATION_VERSION) {
            return;
        }

        if ($version < 2) {
            $this->migrateLegacySkills();
        }

        // Below version 3 (the only case left here), status was in the settings.
        $this->migrateAgentStatuses();

        update_option(self::MIGRATION_OPTION, (string) self::MIGRATION_VERSION, false);
    }

    /**
     * Skills used to be their own post type. Moves them into knowledge, typed "skill".
     * Revisions follow their parent, so history is kept.
     */
    private function migrateLegacySkills(): void
    {
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
    }

    /**
     * Agents used to be always published, with "active" or "paused" in their settings.
     * Now the post status says it: published agents that were not active become paused.
     */
    private function migrateAgentStatuses(): void
    {
        $ids = get_posts(
            [
                'post_type' => PostTypes::AGENT,
                'post_status' => 'publish',
                'numberposts' => -1,
                'fields' => 'ids',
                'suppress_filters' => true,
            ]
        );

        global $wpdb;

        foreach ($ids as $id) {
            if ((AgentConfigMapper::read((int) $id)['status'] ?? '') === 'active') {
                continue;
            }

            // Directly, so the change keeps the agent's modified date and starts nothing.
            $wpdb->update($wpdb->posts, ['post_status' => PostTypes::STATUS_PAUSED], ['ID' => (int) $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            clean_post_cache((int) $id);
        }
    }

    /**
     * Knowledge has no draft workflow. Saving it saves it. (Agents do: a draft is an agent being set up.)
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function keepPublished(array $data): array
    {
        if (($data['post_type'] ?? '') === PostTypes::KNOWLEDGE && in_array($data['post_status'] ?? '', ['draft', 'pending'], true)) {
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
