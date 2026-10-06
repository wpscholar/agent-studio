<?php

declare(strict_types=1);

namespace WPAgents\Infrastructure;

use InvalidArgumentException;
use RuntimeException;
use WP_Post;
use WPAgents\Contracts\SkillRepository;
use WPAgents\Domain\Skill\Skill;

/**
 * Skills are posts of type wpa_skill: the slug is the post name, the title is
 * the skill name, the excerpt is the description and the content is the body.
 */
final class WordPressSkillRepository implements SkillRepository
{
    public function all(): array
    {
        $posts = get_posts(
            [
                'post_type' => PostTypes::SKILL,
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
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
            'post_type' => PostTypes::SKILL,
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
            throw new RuntimeException($result->get_error_message());
        }
    }

    private function findPost(string $slug): ?WP_Post
    {
        $posts = get_posts(
            [
                'post_type' => PostTypes::SKILL,
                'post_status' => ['publish', 'draft', 'private'],
                'name' => $slug,
                'numberposts' => 1,
                'suppress_filters' => true,
            ]
        );

        $post = $posts[0] ?? null;

        return $post instanceof WP_Post ? $post : null;
    }

    private function hydrate(WP_Post $post): ?Skill
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
}
