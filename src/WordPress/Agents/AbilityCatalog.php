<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Agents;

use AgentStudio\Contracts\AbilityGateway;
use AgentStudio\Contracts\AgentRepository;
use AgentStudio\Domain\Ability\AbilityInfo;
use AgentStudio\Domain\Ability\Risk;

/**
 * The abilities agents can be given, searchable and in pages, so the screens
 * stay quick with thousands of them.
 */
final class AbilityCatalog
{
    /** Abilities from connected services are named "mcp-<connection>/<tool>". */
    private const CONNECTION_PREFIX = 'mcp-';

    public function __construct(
        private readonly AbilityGateway $abilities,
        private readonly AgentRepository $agents,
    ) {
    }

    /**
     * @param array{search?: string, category?: string, risk?: string, source?: string} $filters
     *
     * @return array{items: list<array<string, mixed>>, total: int, categories: list<array{value: string, label: string, count: int}>}
     */
    public function search(array $filters, int $page, int $perPage): array
    {
        $term = self::lower(trim($filters['search'] ?? ''));
        $category = $filters['category'] ?? '';
        $risk = Risk::tryFrom($filters['risk'] ?? '');
        $source = $filters['source'] ?? '';
        $usage = $this->usage();

        $matches = [];
        $categories = [];
        foreach ($this->abilities->available() as $info) {
            if ($risk !== null && $info->risk !== $risk) {
                continue;
            }
            if ($source !== '' && self::source($info) !== $source) {
                continue;
            }
            if ($term !== '' && ! str_contains(self::lower($info->name . ' ' . $info->label . ' ' . $info->description . ' ' . $info->category), $term)) {
                continue;
            }

            // Category counts follow the other filters, so they say how many each choice would show.
            $categories[$info->category] = ($categories[$info->category] ?? 0) + 1;
            if ($category === '' || $info->category === $category) {
                $matches[] = $info;
            }
        }

        $categoryList = [];
        foreach ($categories as $value => $count) {
            $categoryList[] = ['value' => (string) $value, 'label' => self::categoryLabel((string) $value), 'count' => $count];
        }
        usort($categoryList, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return [
            'items' => array_map(
                fn (AbilityInfo $info): array => self::describe($info, $usage[$info->name] ?? 0),
                array_slice($matches, ($page - 1) * $perPage, $perPage)
            ),
            'total' => count($matches),
            'categories' => $categoryList,
        ];
    }

    /**
     * The named abilities, in the given order. Names that are not registered right now come back marked missing.
     *
     * @param list<string> $names
     *
     * @return list<array<string, mixed>>
     */
    public function describeNames(array $names): array
    {
        $known = [];
        foreach ($this->abilities->available() as $info) {
            $known[$info->name] = $info;
        }

        $items = [];
        foreach ($names as $name) {
            $items[] = isset($known[$name])
                ? self::describe($known[$name])
                : ['name' => $name, 'label' => $name, 'description' => '', 'category' => '', 'categoryLabel' => '', 'risk' => '', 'source' => '', 'missing' => true];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(AbilityInfo $info, ?int $usedBy = null): array
    {
        $item = [
            'name' => $info->name,
            'label' => $info->label !== '' ? $info->label : $info->name,
            'description' => $info->description,
            'category' => $info->category,
            'categoryLabel' => self::categoryLabel($info->category),
            'risk' => $info->risk->value,
            'source' => self::source($info),
            'missing' => false,
        ];

        if ($usedBy !== null) {
            $item['usedBy'] = $usedBy;
        }

        return $item;
    }

    /**
     * "connection" for tools from connected services, "site" for everything registered on this site.
     */
    private static function source(AbilityInfo $info): string
    {
        return str_starts_with($info->name, self::CONNECTION_PREFIX) ? 'connection' : 'site';
    }

    private static function categoryLabel(string $category): string
    {
        if ($category === '') {
            return __('Other', 'agent-studio');
        }

        if (function_exists('wp_get_ability_category')) {
            $registered = wp_get_ability_category($category);
            if (is_object($registered) && method_exists($registered, 'get_label')) {
                return (string) $registered->get_label();
            }
        }

        // Connection tools use the connection's name as their category; slugs read better with spaces.
        return str_contains($category, ' ') ? $category : ucfirst(str_replace(['-', '_'], ' ', $category));
    }

    /**
     * How many agents may use each ability.
     *
     * @return array<string, int>
     */
    private function usage(): array
    {
        $usage = [];
        foreach ($this->agents->all() as $agent) {
            foreach ($agent->abilities as $name) {
                $usage[$name] = ($usage[$name] ?? 0) + 1;
            }
        }

        return $usage;
    }

    private static function lower(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }
}
