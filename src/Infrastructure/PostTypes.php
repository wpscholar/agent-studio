<?php

declare(strict_types=1);

namespace WPAgents\Infrastructure;

/**
 * Names of the content types and storage keys the plugin uses.
 */
final class PostTypes
{
    public const AGENT = 'wpa_agent';
    public const SKILL = 'wpa_skill';

    /** Post meta holding an agent's configuration as JSON. */
    public const AGENT_CONFIG_META = '_wpa_config';

    /** Post meta holding notes agents left on a post. */
    public const NOTES_META = '_wpa_notes';
}
