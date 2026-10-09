<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Ability;

/**
 * Works out how risky an ability is from its metadata.
 *
 * An ability can state its risk outright with meta agent_studio.risk. Otherwise the
 * standard annotations decide, and anything that does not say it is read-only
 * is treated as a write. Unknown means "ask first".
 */
final class RiskClassifier
{
    /**
     * @param array<string, mixed> $meta The ability's meta array.
     */
    public static function fromMeta(array $meta): Risk
    {
        $agent_studio = $meta['agent_studio'] ?? null;
        if (is_array($agent_studio) && is_string($agent_studio['risk'] ?? null)) {
            $declared = Risk::tryFrom(strtolower($agent_studio['risk']));
            if ($declared !== null) {
                return $declared;
            }
        }

        $annotations = $meta['annotations'] ?? null;
        if (is_array($annotations)) {
            if (($annotations['readonly'] ?? null) === true) {
                return Risk::Read;
            }

            if (($annotations['destructive'] ?? null) === true) {
                return Risk::Destructive;
            }
        }

        return Risk::Write;
    }
}
