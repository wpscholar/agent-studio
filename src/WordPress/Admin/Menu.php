<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\Infrastructure\PostTypes;
use AgentStudio\WordPress\Capabilities;

/**
 * Adds the plugin's pages under the Agents menu.
 */
final class Menu
{
    public function __construct(
        private readonly RunsPage $runs,
        private readonly AbilitiesPage $abilities,
        private readonly TemplatesPage $templates,
        private readonly ImportSkillPage $importSkill,
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

        add_submenu_page($parent, __('Agent runs', 'agent-studio'), __('Runs', 'agent-studio'), $capability, 'agent-studio-runs', [$this->runs, 'render']);
        add_submenu_page($parent, __('Agent templates', 'agent-studio'), __('Templates', 'agent-studio'), $capability, 'agent-studio-templates', [$this->templates, 'render']);
        add_submenu_page($parent, __('Abilities and readiness', 'agent-studio'), __('Abilities', 'agent-studio'), $capability, 'agent-studio-abilities', [$this->abilities, 'render']);
        add_submenu_page($parent, __('Import a skill', 'agent-studio'), __('Import skill', 'agent-studio'), $capability, 'agent-studio-import-skill', [$this->importSkill, 'render']);
    }
}
