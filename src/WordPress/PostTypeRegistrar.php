<?php

declare(strict_types=1);

namespace WPAgents\WordPress;

use WPAgents\Infrastructure\PostTypes;

/**
 * Registers the agent and skill content types.
 *
 * Both are private: they have an admin screen and no front end. Everything
 * requires the manage capability, which is administrators by default.
 */
final class PostTypeRegistrar
{
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
                    'name' => __('Agents', 'wp-agents'),
                    'singular_name' => __('Agent', 'wp-agents'),
                    'menu_name' => __('Agents', 'wp-agents'),
                    'add_new' => __('Add New', 'wp-agents'),
                    'add_new_item' => __('Add New Agent', 'wp-agents'),
                    'edit_item' => __('Edit Agent', 'wp-agents'),
                    'new_item' => __('New Agent', 'wp-agents'),
                    'view_item' => __('View Agent', 'wp-agents'),
                    'search_items' => __('Search Agents', 'wp-agents'),
                    'not_found' => __('No agents yet.', 'wp-agents'),
                    'not_found_in_trash' => __('No agents in the Trash.', 'wp-agents'),
                    'all_items' => __('All Agents', 'wp-agents'),
                ],
                'description' => __('Automations that use AI and your site abilities.', 'wp-agents'),
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
                // Never turn this on: with these capabilities it would make "manage_options"
                // behave like a per-post capability for every check on the site.
                'map_meta_cap' => false,
            ]
        );

        register_post_type(
            PostTypes::SKILL,
            [
                'labels' => [
                    'name' => __('Skills', 'wp-agents'),
                    'singular_name' => __('Skill', 'wp-agents'),
                    'menu_name' => __('Skills', 'wp-agents'),
                    'add_new' => __('Add New', 'wp-agents'),
                    'add_new_item' => __('Add New Skill', 'wp-agents'),
                    'edit_item' => __('Edit Skill', 'wp-agents'),
                    'new_item' => __('New Skill', 'wp-agents'),
                    'view_item' => __('View Skill', 'wp-agents'),
                    'search_items' => __('Search Skills', 'wp-agents'),
                    'not_found' => __('No skills yet.', 'wp-agents'),
                    'not_found_in_trash' => __('No skills in the Trash.', 'wp-agents'),
                    'all_items' => __('Skills', 'wp-agents'),
                ],
                'description' => __('Reusable instructions that agents can load when they need them.', 'wp-agents'),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => 'edit.php?post_type=' . PostTypes::AGENT,
                'show_in_rest' => false,
                'supports' => ['title', 'editor', 'excerpt'],
                'has_archive' => false,
                'rewrite' => false,
                'query_var' => false,
                'can_export' => true,
                'capabilities' => $this->capabilities(),
                // Never turn this on: with these capabilities it would make "manage_options"
                // behave like a per-post capability for every check on the site.
                'map_meta_cap' => false,
            ]
        );
    }

    /**
     * Agents and skills are plain text, so the classic editor is the right tool.
     */
    public function useClassicEditor(bool $useBlockEditor, string $postType): bool
    {
        return in_array($postType, [PostTypes::AGENT, PostTypes::SKILL], true) ? false : $useBlockEditor;
    }

    /**
     * Agents and skills have no draft workflow. Saving them saves them.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function keepPublished(array $data): array
    {
        $isOurs = in_array($data['post_type'] ?? '', [PostTypes::AGENT, PostTypes::SKILL], true);
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
