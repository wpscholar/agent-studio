<?php

declare(strict_types=1);

namespace WPAgents\Domain\Ability;

/**
 * Maps ability names ("namespace/ability-name") to model tool names.
 *
 * Model providers only accept tool names matching [A-Za-z0-9_-]{1,64}, so the
 * namespace separator cannot be used as is.
 */
final class ToolNames
{
    public const MAX_LENGTH = 64;

    public static function forAbility(string $abilityName): string
    {
        $name = str_replace('/', '__', $abilityName);
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $name) ?? '';

        if (strlen($name) > self::MAX_LENGTH) {
            $name = substr($name, 0, self::MAX_LENGTH - 9) . '_' . substr(md5($abilityName), 0, 8);
        }

        return $name;
    }
}
