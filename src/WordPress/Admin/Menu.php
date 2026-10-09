<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\Infrastructure\PostTypes;
use AgentStudio\WordPress\Capabilities;

/**
 * Adds the plugin's pages under the Agents menu, in the order people use them:
 * agents, then what agents use (skills), then what they did (runs).
 */
final class Menu
{
    public const SKILLS = 'agent-studio-skills';

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

        add_submenu_page($parent, __('Skills', 'agent-studio'), __('Skills', 'agent-studio'), $capability, self::SKILLS, [$skills, 'render'], 5);
        add_submenu_page($parent, __('Agent runs', 'agent-studio'), __('Runs', 'agent-studio'), $capability, 'agent-studio-runs', [$this->runs, 'render'], 20);
        add_submenu_page($parent, __('Agent templates', 'agent-studio'), __('Templates', 'agent-studio'), $capability, 'agent-studio-templates', [$this->templates, 'render'], 30);
        add_submenu_page($parent, __('Abilities and readiness', 'agent-studio'), __('Abilities', 'agent-studio'), $capability, 'agent-studio-abilities', [$this->abilities, 'render'], 40);
    }
}
