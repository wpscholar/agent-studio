<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Domain\Agent\Agent;
use AgentStudio\Domain\Agent\AgentStatus;
use AgentStudio\Domain\Agent\ApprovalMode;
use AgentStudio\Domain\Agent\Schedule;
use AgentStudio\Domain\Agent\Trigger;
use AgentStudio\Domain\Agent\TriggerType;

/**
 * Converts between an agent's stored configuration (a JSON array in post meta)
 * and the Agent object, and cleans configuration coming from forms or imports.
 */
final class AgentConfigMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'status' => AgentStatus::Paused->value,
            'triggers' => [],
            'abilities' => [],
            'skills' => [],
            'approval' => ApprovalMode::Writes->value,
            'run_as' => 0,
            'max_steps' => 10,
            'max_runs_per_hour' => 30,
            'provider' => '',
            'model' => '',
            'webhook_secret' => '',
        ];
    }

    /**
     * Cleans raw configuration: unknown keys are dropped, values are coerced and clamped.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $config): array
    {
        $config = array_merge(self::defaults(), $config);

        $triggers = [];
        $seen = [];
        foreach (is_array($config['triggers']) ? $config['triggers'] : [] as $raw) {
            $trigger = is_array($raw) ? Trigger::fromArray($raw) : null;
            if ($trigger === null || isset($seen[$trigger->type->value])) {
                continue;
            }

            $seen[$trigger->type->value] = true;
            $triggers[] = ['type' => $trigger->type->value, 'config' => self::cleanTriggerConfig($trigger)];
        }

        return [
            'status' => AgentStatus::fromString(self::string($config['status']))->value,
            'triggers' => $triggers,
            'abilities' => self::slugs($config['abilities'], '/^[A-Za-z0-9][A-Za-z0-9_.-]*\/[A-Za-z0-9][A-Za-z0-9_.\/-]*$/'),
            'skills' => self::slugs($config['skills'], '/^[a-z0-9][a-z0-9-]{0,63}$/'),
            'approval' => ApprovalMode::fromString(self::string($config['approval']))->value,
            'run_as' => max(0, (int) self::number($config['run_as'])),
            'max_steps' => max(Agent::MIN_STEPS, min(Agent::MAX_STEPS, (int) self::number($config['max_steps']))),
            'max_runs_per_hour' => max(1, min(Agent::MAX_RUNS_PER_HOUR_LIMIT, (int) self::number($config['max_runs_per_hour']))),
            'provider' => preg_replace('/[^a-z0-9_-]/', '', strtolower(self::string($config['provider']))) ?? '',
            'model' => substr(trim(self::string($config['model'])), 0, 100),
            'webhook_secret' => preg_replace('/[^A-Za-z0-9]/', '', self::string($config['webhook_secret'])) ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function toAgent(int $id, string $name, string $instructions, array $config): Agent
    {
        $config = self::normalize($config);

        $triggers = [];
        foreach ($config['triggers'] as $raw) {
            $trigger = Trigger::fromArray($raw);
            if ($trigger !== null) {
                $triggers[] = $trigger;
            }
        }

        return new Agent(
            $id,
            $name,
            $instructions,
            AgentStatus::fromString($config['status']),
            $triggers,
            $config['abilities'],
            $config['skills'],
            ApprovalMode::fromString($config['approval']),
            $config['run_as'],
            $config['max_steps'],
            $config['max_runs_per_hour'],
            $config['model'],
            $config['webhook_secret'],
            $config['provider']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(int $postId): array
    {
        $raw = get_post_meta($postId, PostTypes::AGENT_CONFIG_META, true);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function write(int $postId, array $config): void
    {
        update_post_meta(
            $postId,
            PostTypes::AGENT_CONFIG_META,
            wp_slash((string) wp_json_encode(self::normalize($config), JSON_UNESCAPED_UNICODE))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function cleanTriggerConfig(Trigger $trigger): array
    {
        if ($trigger->type === TriggerType::Schedule) {
            $schedule = Schedule::fromConfig($trigger->config);

            return $schedule === null ? ['every' => Schedule::DAILY, 'at' => '09:00'] : $schedule->toConfig();
        }

        $clean = [];
        foreach (['post_types', 'from', 'to', 'roles'] as $key) {
            $list = $trigger->list($key);
            if ($list !== []) {
                $clean[$key] = array_map(
                    static fn (string $value): string => substr(sanitize_key($value), 0, 60),
                    $list
                );
            }
        }

        return $clean;
    }

    /**
     * @return list<string>
     */
    private static function slugs(mixed $value, string $pattern): array
    {
        if (! is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $item = is_string($item) ? trim($item) : '';
            if ($item !== '' && preg_match($pattern, $item) === 1) {
                $clean[$item] = $item;
            }
        }

        return array_values($clean);
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function number(mixed $value): int|float
    {
        return is_numeric($value) ? $value + 0 : 0;
    }
}
