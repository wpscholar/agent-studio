<?php

declare(strict_types=1);

namespace WPAgents\Domain\Ability;

/**
 * Works out how risky an ability is from its metadata.
 *
 * An ability can state its risk outright with meta wpa.risk. Otherwise the
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
        $wpa = $meta['wpa'] ?? null;
        if (is_array($wpa) && is_string($wpa['risk'] ?? null)) {
            $declared = Risk::tryFrom(strtolower($wpa['risk']));
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
