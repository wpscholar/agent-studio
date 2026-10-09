<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Admin;

use Throwable;
use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\WordPress\Capabilities;
use AgentStudio\WordPress\Labels;

/**
 * Shows what agents can be given, and whether this site is ready to run them.
 */
final class AbilitiesPage
{
    public function __construct(private readonly AbilityGateway $abilities)
    {
    }

    public function render(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to see this page.', 'agent-studio'), '', ['response' => 403]);
        }

        echo '<div class="wrap agent-studio-wrap"><h1>' . esc_html__('Abilities and readiness', 'agent-studio') . '</h1>';

        echo '<h2>' . esc_html__('Is this site ready?', 'agent-studio') . '</h2><table class="widefat striped agent-studio-meta"><tbody>';
        $this->check(
            __('Abilities API', 'agent-studio'),
            function_exists('wp_get_abilities'),
            __('Available.', 'agent-studio'),
            __('Not found. Agent Studio needs WordPress 6.9 or newer.', 'agent-studio')
        );
        $this->check(
            __('AI Client', 'agent-studio'),
            function_exists('wp_ai_client_prompt'),
            __('Available.', 'agent-studio'),
            __('Not found. Agent Studio needs WordPress 7.0 or newer.', 'agent-studio')
        );
        $this->check(
            __('AI features switched on', 'agent-studio'),
            ! function_exists('wp_supports_ai') || wp_supports_ai(),
            __('Yes.', 'agent-studio'),
            __('AI is turned off for this site.', 'agent-studio')
        );
        $this->check(
            __('A model that can use tools', 'agent-studio'),
            $this->modelReady(),
            __('A connected provider supports text generation.', 'agent-studio'),
            __('No connected AI provider was found. Add one in your AI connectors settings.', 'agent-studio')
        );
        echo '</tbody></table>';

        $available = $this->abilities->available();
        echo '<h2>' . esc_html(sprintf(
            /* translators: %d: number of abilities */
            _n('%d ability agents can use', '%d abilities agents can use', count($available), 'agent-studio'),
            count($available)
        )) . '</h2>';
        echo '<p class="description">' . esc_html__('Other plugins add abilities here. An agent only gets the ones you tick on its edit screen.', 'agent-studio') . '</p>';

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        foreach ([__('Ability', 'agent-studio'), __('Name', 'agent-studio'), __('Category', 'agent-studio'), __('Risk', 'agent-studio')] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($available as $info) {
            printf(
                '<tr><td><strong>%1$s</strong><br><span class="description">%2$s</span></td><td><code>%3$s</code></td><td>%4$s</td><td><span class="agent-studio-risk agent-studio-risk--%5$s">%6$s</span></td></tr>',
                esc_html($info->label),
                esc_html($info->description),
                esc_html($info->name),
                esc_html($info->category),
                esc_attr($info->risk->value),
                esc_html(Labels::risk($info->risk))
            );
        }

        if ($available === []) {
            echo '<tr><td colspan="4">' . esc_html__('No abilities are registered.', 'agent-studio') . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    private function modelReady(): bool
    {
        if (! function_exists('wp_ai_client_prompt')) {
            return false;
        }

        try {
            $builder = wp_ai_client_prompt('ping');

            return method_exists($builder, 'is_supported_for_text_generation')
                ? (bool) $builder->is_supported_for_text_generation()
                : true;
        } catch (Throwable) {
            return false;
        }
    }

    private function check(string $label, bool $ok, string $good, string $bad): void
    {
        printf(
            '<tr><th scope="row">%1$s</th><td><span class="agent-studio-dot agent-studio-dot--%2$s"></span> %3$s</td></tr>',
            esc_html($label),
            $ok ? 'ok' : 'bad',
            esc_html($ok ? $good : $bad)
        );
    }
}
