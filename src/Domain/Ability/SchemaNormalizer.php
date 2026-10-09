<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Ability;

/**
 * Turns an ability's input schema into one a model provider accepts.
 *
 * Abilities may use WordPress REST-style schemas, which differ from standard
 * JSON Schema in two ways that providers reject: a boolean "required" on a
 * property, and a root schema that is not an object.
 */
final class SchemaNormalizer
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array{schema: array<string, mixed>, wrapped: bool}
     */
    public static function forModel(array $schema): array
    {
        if ($schema === []) {
            return ['schema' => [], 'wrapped' => false];
        }

        $schema = self::clean($schema);

        if (($schema['type'] ?? null) === 'object') {
            return ['schema' => $schema, 'wrapped' => false];
        }

        return [
            'schema' => [
                'type' => 'object',
                'properties' => ['input' => $schema],
                'required' => ['input'],
            ],
            'wrapped' => true,
        ];
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private static function clean(array $node): array
    {
        $required = [];
        if (isset($node['required']) && is_array($node['required'])) {
            $required = array_values(array_filter($node['required'], 'is_string'));
        }

        if (isset($node['properties']) && is_array($node['properties'])) {
            foreach ($node['properties'] as $key => $property) {
                if (! is_array($property)) {
                    continue;
                }

                if (($property['required'] ?? null) === true) {
                    $required[] = (string) $key;
                }

                $node['properties'][$key] = self::clean($property);
            }
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $node['items'] = self::clean($node['items']);
        }

        if (isset($node['required']) && ! is_array($node['required'])) {
            unset($node['required']);
        }

        if ($required !== []) {
            $node['required'] = array_values(array_unique($required));
        } else {
            unset($node['required']);
        }

        return $node;
    }
}
