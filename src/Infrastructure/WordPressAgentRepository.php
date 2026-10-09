<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use WP_Post;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\TriggerType;

/**
 * Agents are posts of type agent_studio_agent: the title is the name, the content is the
 * instructions, and everything else is a JSON blob in post meta.
 */
final class WordPressAgentRepository implements AgentRepository
{
    public function find(int $id): ?Agent
    {
        $post = get_post($id);

        if (! $post instanceof WP_Post || $post->post_type !== PostTypes::AGENT || $post->post_status === 'trash') {
            return null;
        }

        return $this->hydrate($post);
    }

    public function findActiveByTrigger(TriggerType $type): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Agent $agent): bool => $agent->isActive() && $agent->hasTrigger($type)
        ));
    }

    public function all(): array
    {
        $posts = get_posts(
            [
                'post_type' => PostTypes::AGENT,
                'post_status' => ['publish', 'draft', 'private', 'pending'],
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'suppress_filters' => true,
            ]
        );

        $agents = [];
        foreach ($posts as $post) {
            if ($post instanceof WP_Post) {
                $agents[] = $this->hydrate($post);
            }
        }

        return $agents;
    }

    private function hydrate(WP_Post $post): Agent
    {
        return AgentConfigMapper::toAgent(
            $post->ID,
            $post->post_title !== '' ? $post->post_title : sprintf('Agent #%d', $post->ID),
            $post->post_content,
            AgentConfigMapper::read($post->ID)
        );
    }
}
