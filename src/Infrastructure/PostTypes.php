<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

/**
 * Names of the content types and storage keys the plugin uses.
 */
final class PostTypes
{
    public const AGENT = 'agent_studio_agent';
    public const SKILL = 'agent_studio_skill';

    /** Post meta holding an agent's configuration as JSON. */
    public const AGENT_CONFIG_META = '_agent_studio_config';

    /** Post meta holding notes agents left on a post. */
    public const NOTES_META = '_agent_studio_notes';
}
