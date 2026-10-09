<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use WP_Ability;
use AgentStudio\Contracts\Ability\AbilityResult;
use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Domain\Ability\AbilityInfo;
use AgentStudio\Domain\Ability\RiskClassifier;
use AgentStudio\Domain\Ability\SchemaNormalizer;

/**
 * Exposes the abilities registered with the WordPress Abilities API to agents.
 */
final class AbilitiesApiGateway implements AbilityGateway
{
    /**
     * Abilities agents may never call. Starting another agent from an agent is
     * how loops start, so that is left for a later version with proper limits.
     */
    private const EXCLUDED = ['agent-studio/run-agent'];

    public function available(): array
    {
        if (! function_exists('wp_get_abilities')) {
            return [];
        }

        $infos = [];
        foreach (wp_get_abilities() as $ability) {
            if ($ability instanceof WP_Ability && $this->isUsable($ability->get_name())) {
                $infos[] = $this->describe($ability);
            }
        }

        usort($infos, static fn (AbilityInfo $a, AbilityInfo $b): int => strcmp($a->name, $b->name));

        return $infos;
    }

    public function execute(string $abilityName, array $input): AbilityResult
    {
        if (! function_exists('wp_get_ability')) {
            return AbilityResult::failure('The Abilities API is not available.');
        }

        $ability = wp_get_ability($abilityName);
        if (! $ability instanceof WP_Ability || ! $this->isUsable($abilityName)) {
            return AbilityResult::failure(sprintf('The ability "%s" is not available.', $abilityName));
        }

        $normalized = SchemaNormalizer::forModel($ability->get_input_schema());
        if ($ability->get_input_schema() === []) {
            $payload = null;
        } elseif ($normalized['wrapped']) {
            $payload = $input['input'] ?? null;
        } else {
            $payload = $input;
        }

        $result = $ability->execute($payload);

        if (is_wp_error($result)) {
            return AbilityResult::failure($result->get_error_code() . ': ' . $result->get_error_message());
        }

        return AbilityResult::success($result);
    }

    private function isUsable(string $name): bool
    {
        if (in_array($name, self::EXCLUDED, true)) {
            return false;
        }

        /**
         * Filters whether agents may use an ability at all.
         *
         * Return false to hide an ability from every agent, whatever its allowlist says.
         *
         * @param bool   $usable Whether agents may use the ability. Default true.
         * @param string $name   The ability name.
         */
        return (bool) apply_filters('agent_studio_agent_can_use_ability', true, $name);
    }

    private function describe(WP_Ability $ability): AbilityInfo
    {
        $normalized = SchemaNormalizer::forModel($ability->get_input_schema());

        return new AbilityInfo(
            $ability->get_name(),
            $ability->get_label(),
            $ability->get_description(),
            $ability->get_category(),
            RiskClassifier::fromMeta($ability->get_meta()),
            $normalized['schema'],
            $normalized['wrapped']
        );
    }
}
