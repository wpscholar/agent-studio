<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\Infrastructure\PostTypes;
use AgentStudio\WordPress\Capabilities;

/**
 * Adds the plugin's pages under the Agents menu, in the order people use them:
 * agents, then what agents use (skills, connections), where people reach
 * them (channels), then what they did (runs).
 */
final class Menu
{
    public const SKILLS = 'agent-studio-skills';
    public const CONNECTIONS = 'agent-studio-connections';
    public const CHANNELS = 'agent-studio-channels';

    public function __construct(
        private readonly RunsPage $runs,
        private readonly AbilitiesPage $abilities,
        private readonly TemplatesPage $templates,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addPages']);
    }

    public function addPages(): void
    {
        $parent = 'edit.php?post_type=' . PostTypes::AGENT;
        $capability = Capabilities::manage();
        $skills = new AppScreen('skills', __('Skills', 'agent-studio'));
        $connections = new AppScreen('connections', __('Connections', 'agent-studio'));
        $channels = new AppScreen('channels', __('Channels', 'agent-studio'));

        add_submenu_page($parent, __('Skills', 'agent-studio'), __('Skills', 'agent-studio'), $capability, self::SKILLS, [$skills, 'render'], 5);
        add_submenu_page($parent, __('Connections', 'agent-studio'), __('Connections', 'agent-studio'), $capability, self::CONNECTIONS, [$connections, 'render'], 10);
        add_submenu_page($parent, __('Channels', 'agent-studio'), __('Channels', 'agent-studio'), $capability, self::CHANNELS, [$channels, 'render'], 15);
        add_submenu_page($parent, __('Agent runs', 'agent-studio'), __('Runs', 'agent-studio'), $capability, 'agent-studio-runs', [$this->runs, 'render'], 20);
        add_submenu_page($parent, __('Agent templates', 'agent-studio'), __('Templates', 'agent-studio'), $capability, 'agent-studio-templates', [$this->templates, 'render'], 30);
        add_submenu_page($parent, __('Abilities and readiness', 'agent-studio'), __('Abilities', 'agent-studio'), $capability, 'agent-studio-abilities', [$this->abilities, 'render'], 40);
    }
}
