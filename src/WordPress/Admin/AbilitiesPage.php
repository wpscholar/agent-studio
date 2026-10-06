<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Admin;

use Throwable;
use WPAgents\Contracts\AbilityGateway;
use WPAgents\WordPress\Capabilities;
use WPAgents\WordPress\Labels;

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
            wp_die(esc_html__('You are not allowed to see this page.', 'wp-agents'), '', ['response' => 403]);
        }

        echo '<div class="wrap wpa-wrap"><h1>' . esc_html__('Abilities and readiness', 'wp-agents') . '</h1>';

        echo '<h2>' . esc_html__('Is this site ready?', 'wp-agents') . '</h2><table class="widefat striped wpa-meta"><tbody>';
        $this->check(
            __('Abilities API', 'wp-agents'),
            function_exists('wp_get_abilities'),
            __('Available.', 'wp-agents'),
            __('Not found. WP Agents needs WordPress 6.9 or newer.', 'wp-agents')
        );
        $this->check(
            __('AI Client', 'wp-agents'),
            function_exists('wp_ai_client_prompt'),
            __('Available.', 'wp-agents'),
            __('Not found. WP Agents needs WordPress 7.0 or newer.', 'wp-agents')
        );
        $this->check(
            __('AI features switched on', 'wp-agents'),
            ! function_exists('wp_supports_ai') || wp_supports_ai(),
            __('Yes.', 'wp-agents'),
            __('AI is turned off for this site.', 'wp-agents')
        );
        $this->check(
            __('A model that can use tools', 'wp-agents'),
            $this->modelReady(),
            __('A connected provider supports text generation.', 'wp-agents'),
            __('No connected AI provider was found. Add one in your AI connectors settings.', 'wp-agents')
        );
        echo '</tbody></table>';

        $available = $this->abilities->available();
        echo '<h2>' . esc_html(sprintf(
            /* translators: %d: number of abilities */
            _n('%d ability agents can use', '%d abilities agents can use', count($available), 'wp-agents'),
            count($available)
        )) . '</h2>';
        echo '<p class="description">' . esc_html__('Other plugins add abilities here. An agent only gets the ones you tick on its edit screen.', 'wp-agents') . '</p>';

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        foreach ([__('Ability', 'wp-agents'), __('Name', 'wp-agents'), __('Category', 'wp-agents'), __('Risk', 'wp-agents')] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($available as $info) {
            printf(
                '<tr><td><strong>%1$s</strong><br><span class="description">%2$s</span></td><td><code>%3$s</code></td><td>%4$s</td><td><span class="wpa-risk wpa-risk--%5$s">%6$s</span></td></tr>',
                esc_html($info->label),
                esc_html($info->description),
                esc_html($info->name),
                esc_html($info->category),
                esc_attr($info->risk->value),
                esc_html(Labels::risk($info->risk))
            );
        }

        if ($available === []) {
            echo '<tr><td colspan="4">' . esc_html__('No abilities are registered.', 'wp-agents') . '</td></tr>';
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
            '<tr><th scope="row">%1$s</th><td><span class="wpa-dot wpa-dot--%2$s"></span> %3$s</td></tr>',
            esc_html($label),
            $ok ? 'ok' : 'bad',
            esc_html($ok ? $good : $bad)
        );
    }
}
