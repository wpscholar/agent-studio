<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use InvalidArgumentException;
use RuntimeException;
use WP_Post;
use AgentStudio\Contracts\SkillRepository;
use AgentStudio\Domain\Skill\Skill;

/**
 * Skills are knowledge items typed "skill": the slug is the post name, the title is
 * the skill name, the excerpt is the description and the content is the body.
 */
final class WordPressSkillRepository implements SkillRepository
{
    public function all(): array
    {
        $posts = get_posts(
            [
                'post_type' => PostTypes::KNOWLEDGE,
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'tax_query' => self::skillsOnly(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                'suppress_filters' => true,
            ]
        );

        $skills = [];
        foreach ($posts as $post) {
            $skill = $post instanceof WP_Post ? $this->hydrate($post) : null;
            if ($skill !== null) {
                $skills[] = $skill;
            }
        }

        return $skills;
    }

    public function findBySlug(string $slug): ?Skill
    {
        $post = $this->findPost($slug);

        return $post === null ? null : $this->hydrate($post);
    }

    public function save(Skill $skill): void
    {
        $data = [
            'post_type' => PostTypes::KNOWLEDGE,
            'post_status' => 'publish',
            'post_name' => $skill->slug,
            'post_title' => wp_slash($skill->name),
            'post_excerpt' => wp_slash($skill->description),
            'post_content' => wp_slash($skill->body),
        ];

        $existing = $this->findPost($skill->slug);
        if ($existing !== null) {
            $data['ID'] = $existing->ID;
        }

        $result = wp_insert_post($data, true);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; escaped by the caller on display.
        }

        wp_set_object_terms($result, PostTypes::TYPE_SKILL, PostTypes::KNOWLEDGE_TYPE);

        // WordPress keeps a copy only when a post changes, so the first version would be lost.
        if ($existing === null) {
            wp_save_post_revision($result);
        }
    }

    public function delete(string $slug): void
    {
        $post = $this->findPost($slug);
        if ($post !== null) {
            wp_delete_post($post->ID, true);
        }
    }

    /**
     * The post behind a skill, for screens that also show dates and history.
     */
    public function findPost(string $slug): ?WP_Post
    {
        $posts = get_posts(
            [
                'post_type' => PostTypes::KNOWLEDGE,
                'post_status' => ['publish', 'draft', 'private'],
                'name' => $slug,
                'numberposts' => 1,
                'tax_query' => self::skillsOnly(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                'suppress_filters' => true,
            ]
        );

        $post = $posts[0] ?? null;

        return $post instanceof WP_Post ? $post : null;
    }

    public static function isSkillPost(WP_Post $post): bool
    {
        return $post->post_type === PostTypes::KNOWLEDGE
            && has_term(PostTypes::TYPE_SKILL, PostTypes::KNOWLEDGE_TYPE, $post);
    }

    public function hydrate(WP_Post $post): ?Skill
    {
        try {
            return new Skill(
                $post->post_name,
                $post->post_title !== '' ? $post->post_title : $post->post_name,
                $post->post_excerpt,
                $post->post_content
            );
        } catch (InvalidArgumentException) {
            // A slug WordPress produced that the skill rules do not accept (for example non-Latin titles).
            return null;
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private static function skillsOnly(): array
    {
        return [
            [
                'taxonomy' => PostTypes::KNOWLEDGE_TYPE,
                'field' => 'slug',
                'terms' => PostTypes::TYPE_SKILL,
            ],
        ];
    }
}
