<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

/**
 * Names of the content types and storage keys the plugin uses.
 */
final class PostTypes
{
    public const AGENT = 'agent_studio_agent';

    /**
     * Knowledge: skills now, memories and notes later. Same shape as the
     * `wp_knowledge` post type Gutenberg is experimenting with (title, excerpt
     * as the description, content, revisions, typed by a taxonomy), so it can
     * move to core storage if that ships. Post type keys are limited to 20 characters.
     */
    public const KNOWLEDGE = 'agent_studio_kb';
    public const KNOWLEDGE_TYPE = 'agent_studio_kb_type';

    /** Knowledge type term for skills. */
    public const TYPE_SKILL = 'skill';

    /** Skills were their own post type before they became knowledge. */
    public const LEGACY_SKILL = 'agent_studio_skill';

    /** Connections to other services, such as MCP servers. */
    public const CONNECTION = 'agent_studio_conn';

    /** Post meta holding a connection's settings, status and tools as JSON. Never secrets. */
    public const CONNECTION_META = '_agent_studio_connection';

    /** Post meta holding an agent's configuration as JSON. */
    public const AGENT_CONFIG_META = '_agent_studio_config';

    /** Post meta holding notes agents left on a post. */
    public const NOTES_META = '_agent_studio_notes';
}
