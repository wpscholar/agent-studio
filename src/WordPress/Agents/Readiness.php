<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Agents;

use Closure;

/**
 * Checks, each time it is asked, whether this site can run agents, and says
 * what to do about anything that is missing.
 *
 * "error" means agents cannot run until it is fixed; "warning" means they can,
 * with a limitation.
 */
final class Readiness
{
    /** Scheduled tasks this late mean WordPress's scheduler is not running. */
    private const LATE_AFTER = 15 * MINUTE_IN_SECONDS;

    /**
     * @param Closure(): list<array{id: string, name: string, models: list<array{id: string, name: string, tools: bool}>}> $providers
     *        The connected AI providers, as AiModelCatalog::providers() lists them.
     */
    public function __construct(private readonly Closure $providers)
    {
    }

    /**
     * @return array{ready: bool, checks: list<array{key: string, label: string, status: string, message: string, action: ?array{label: string, url: string}}>}
     */
    public function check(): array
    {
        $checks = [
            $this->abilitiesApi(),
            $this->aiClient(),
            $this->aiSwitchedOn(),
            ...$this->providers(),
            $this->background(),
            $this->https(),
        ];

        $ready = true;
        foreach ($checks as $check) {
            $ready = $ready && $check['status'] !== 'error';
        }

        return ['ready' => $ready, 'checks' => $checks];
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, action: ?array{label: string, url: string}}
     */
    private function abilitiesApi(): array
    {
        return function_exists('wp_get_abilities')
            ? self::result('abilities_api', __('Abilities', 'agent-studio'), 'ok', __('WordPress can tell agents what they are able to do.', 'agent-studio'))
            : self::result(
                'abilities_api',
                __('Abilities', 'agent-studio'),
                'error',
                __('This version of WordPress does not have the Abilities API. Update to WordPress 6.9 or newer.', 'agent-studio'),
                self::action(__('Update WordPress', 'agent-studio'), admin_url('update-core.php'))
            );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, action: ?array{label: string, url: string}}
     */
    private function aiClient(): array
    {
        return function_exists('wp_ai_client_prompt')
            ? self::result('ai_client', __('AI support in WordPress', 'agent-studio'), 'ok', __('WordPress can talk to AI models.', 'agent-studio'))
            : self::result(
                'ai_client',
                __('AI support in WordPress', 'agent-studio'),
                'error',
                __('This version of WordPress cannot talk to AI models. Update to WordPress 7.0 or newer.', 'agent-studio'),
                self::action(__('Update WordPress', 'agent-studio'), admin_url('update-core.php'))
            );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, action: ?array{label: string, url: string}}
     */
    private function aiSwitchedOn(): array
    {
        $on = function_exists('wp_supports_ai') ? (bool) wp_supports_ai() : function_exists('wp_ai_client_prompt');

        return $on
            ? self::result('ai_enabled', __('AI features switched on', 'agent-studio'), 'ok', __('AI features are switched on for this site.', 'agent-studio'))
            : self::result(
                'ai_enabled',
                __('AI features switched on', 'agent-studio'),
                'error',
                __('AI features are switched off for this site. A plugin or a setting in wp-config.php usually does this; ask whoever manages your site.', 'agent-studio')
            );
    }

    /**
     * Whether an AI provider is connected, and whether any of its models can use abilities.
     *
     * @return list<array{key: string, label: string, status: string, message: string, action: ?array{label: string, url: string}}>
     */
    private function providers(): array
    {
        $connectors = self::action(__('Open Settings → Connectors', 'agent-studio'), admin_url('options-connectors.php'));
        $providers = ($this->providers)();

        if ($providers === []) {
            return [
                self::result(
                    'provider',
                    __('AI provider connected', 'agent-studio'),
                    'error',
                    __('No AI provider is connected. Connect one, such as Anthropic, Google or OpenAI, with an API key from that company.', 'agent-studio'),
                    $connectors
                ),
                self::result(
                    'tool_model',
                    __('A model that can use abilities', 'agent-studio'),
                    'error',
                    __('Connect an AI provider first.', 'agent-studio')
                ),
            ];
        }

        $names = [];
        $toolModels = 0;
        foreach ($providers as $provider) {
            $names[] = $provider['name'];
            foreach ($provider['models'] as $model) {
                $toolModels += $model['tools'] ? 1 : 0;
            }
        }

        return [
            self::result(
                'provider',
                __('AI provider connected', 'agent-studio'),
                'ok',
                sprintf(
                    /* translators: %s: list of AI provider names, like "Anthropic, Google". */
                    __('Connected: %s.', 'agent-studio'),
                    implode(', ', $names)
                ),
                self::action(__('Manage', 'agent-studio'), admin_url('options-connectors.php'))
            ),
            $toolModels > 0
                ? self::result(
                    'tool_model',
                    __('A model that can use abilities', 'agent-studio'),
                    'ok',
                    sprintf(
                        /* translators: %d: number of AI models */
                        _n('%d connected model can use abilities.', '%d connected models can use abilities.', $toolModels, 'agent-studio'),
                        $toolModels
                    )
                )
                : self::result(
                    'tool_model',
                    __('A model that can use abilities', 'agent-studio'),
                    'error',
                    __('None of the connected models can use abilities, so agents could only reply in words. Connect a provider whose models support tools.', 'agent-studio'),
                    $connectors
                ),
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, action: ?array{label: string, url: string}}
     */
    private function background(): array
    {
        $label = __('Running in the background', 'agent-studio');

        if (function_exists('as_enqueue_async_action')) {
            return self::result('background', $label, 'ok', __('Agents run in the background with Action Scheduler.', 'agent-studio'));
        }

        $late = self::oldestLateTask();
        if ($late > 0) {
            return self::result(
                'background',
                $label,
                'warning',
                sprintf(
                    /* translators: %s: how late, like "2 hours". */
                    __('WordPress’s scheduled tasks are running late (the oldest by %s), so agents may start late. On quiet sites this is normal; for reliable timing, ask your host to run wp-cron.php every few minutes.', 'agent-studio'),
                    human_time_diff(time() - $late)
                )
            );
        }

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            return self::result(
                'background',
                $label,
                'warning',
                __('WordPress’s built-in scheduler is switched off on this site. Agents only start if your server runs wp-cron.php regularly. No tasks are late right now, so it looks like it does.', 'agent-studio')
            );
        }

        return self::result('background', $label, 'ok', __('WordPress’s scheduler is running, so agents start in the background.', 'agent-studio'));
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, action: ?array{label: string, url: string}}
     */
    private function https(): array
    {
        $secure = function_exists('wp_is_using_https') ? wp_is_using_https() : str_starts_with(home_url(), 'https://');

        return $secure
            ? self::result('https', __('Secure address (https)', 'agent-studio'), 'ok', __('Your site uses https, so chat apps like Slack and Telegram can reach it.', 'agent-studio'))
            : self::result(
                'https',
                __('Secure address (https)', 'agent-studio'),
                'warning',
                __('Your site does not use https. Agents still run, but Slack and Telegram will not send messages to it.', 'agent-studio')
            );
    }

    /**
     * Seconds the most overdue scheduled task is late by, or 0 when nothing is late.
     */
    private static function oldestLateTask(): int
    {
        if (! function_exists('_get_cron_array')) {
            return 0;
        }

        $crons = _get_cron_array();
        $oldest = is_array($crons) && $crons !== [] ? (int) min(array_keys($crons)) : 0;
        $late = $oldest > 0 ? time() - $oldest : 0;

        return $late > self::LATE_AFTER ? $late : 0;
    }

    /**
     * @param array{label: string, url: string}|null $action
     *
     * @return array{key: string, label: string, status: string, message: string, action: ?array{label: string, url: string}}
     */
    private static function result(string $key, string $label, string $status, string $message, ?array $action = null): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'message' => $message, 'action' => $action];
    }

    /**
     * @return array{label: string, url: string}
     */
    private static function action(string $label, string $url): array
    {
        return ['label' => $label, 'url' => $url];
    }
}
