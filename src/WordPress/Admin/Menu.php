<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Admin;

use WPAgents\Infrastructure\PostTypes;
use WPAgents\WordPress\Capabilities;

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

        add_submenu_page($parent, __('Agent runs', 'wp-agents'), __('Runs', 'wp-agents'), $capability, 'wpa-runs', [$this->runs, 'render']);
        add_submenu_page($parent, __('Agent templates', 'wp-agents'), __('Templates', 'wp-agents'), $capability, 'wpa-templates', [$this->templates, 'render']);
        add_submenu_page($parent, __('Abilities and readiness', 'wp-agents'), __('Abilities', 'wp-agents'), $capability, 'wpa-abilities', [$this->abilities, 'render']);
        add_submenu_page($parent, __('Import a skill', 'wp-agents'), __('Import skill', 'wp-agents'), $capability, 'wpa-import-skill', [$this->importSkill, 'render']);
    }
}
