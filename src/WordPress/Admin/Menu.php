<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use AgentStudio\WordPress\Capabilities;

/**
 * The Agents menu, in the order people use it: agents, then what agents use
 * (skills, connections), where people reach them (channels), what they did
 * (runs), and what they can be given (abilities).
 */
final class Menu
{
    public const AGENTS = 'agent-studio';
    public const SKILLS = 'agent-studio-skills';
    public const CONNECTIONS = 'agent-studio-connections';
    public const CHANNELS = 'agent-studio-channels';
    public const RUNS = 'agent-studio-runs';
    public const ABILITIES = 'agent-studio-abilities';

    public function __construct(private readonly RunsPage $runs)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addPages']);
    }

    public function addPages(): void
    {
        $capability = Capabilities::manage();
        $agents = new AppScreen('agents', __('Agents', 'agent-studio'));

        add_menu_page(__('Agents', 'agent-studio'), __('Agents', 'agent-studio'), $capability, self::AGENTS, [$agents, 'render'], 'dashicons-superhero', 58);

        $pages = [
            [self::AGENTS, __('Agents', 'agent-studio'), __('All agents', 'agent-studio'), [$agents, 'render']],
            [self::SKILLS, __('Skills', 'agent-studio'), __('Skills', 'agent-studio'), [new AppScreen('skills', __('Skills', 'agent-studio')), 'render']],
            [self::CONNECTIONS, __('Connections', 'agent-studio'), __('Connections', 'agent-studio'), [new AppScreen('connections', __('Connections', 'agent-studio')), 'render']],
            [self::CHANNELS, __('Channels', 'agent-studio'), __('Channels', 'agent-studio'), [new AppScreen('channels', __('Channels', 'agent-studio')), 'render']],
            [self::RUNS, __('Agent runs', 'agent-studio'), __('Runs', 'agent-studio'), [$this->runs, 'render']],
            [self::ABILITIES, __('Abilities', 'agent-studio'), __('Abilities', 'agent-studio'), [new AppScreen('abilities', __('Abilities', 'agent-studio')), 'render']],
        ];

        foreach ($pages as [$slug, $title, $menuTitle, $callback]) {
            add_submenu_page(self::AGENTS, $title, $menuTitle, $capability, $slug, $callback);
        }
    }
}
