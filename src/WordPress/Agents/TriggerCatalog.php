<?php

declare(strict_types=1);

namespace AgentStudio\WordPress\Agents;

use AgentStudio\Application\Triggers\TriggerDefinitions;
use AgentStudio\Domain\Agent\Trigger;
use AgentStudio\Domain\Agent\TriggerType;

/**
 * Everything that can start an agent, described for the Agents screen: the
 * built-in events and every event plugins registered, each with its filter fields.
 *
 * Each entry has a key ("post_status", or "custom:<kind>" for plugin events),
 * a label, a description, a group, and fields. Field types are those of
 * Domain\Setup\SetupField, plus "list" (several values, with suggestions),
 * "schedule" and "webhook", which the screen draws specially.
 */
final class TriggerCatalog
{
    public const CUSTOM_PREFIX = 'custom:';

    public function __construct(private readonly ?TriggerDefinitions $definitions = null)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $postTypes = self::postTypes();
        $statuses = self::postStatuses();
        $content = __('Content', 'agent-studio');

        $entries = [
            [
                'key' => TriggerType::PostStatus->value,
                'label' => __('A post changes status', 'agent-studio'),
                'description' => __('For example when a post is submitted for review, published, or moved to the Trash.', 'agent-studio'),
                'group' => $content,
                'fields' => [
                    self::listField('post_types', __('Which kinds of content', 'agent-studio'), __('Leave empty for all kinds.', 'agent-studio'), $postTypes),
                    self::listField('to', __('Changes to', 'agent-studio'), __('For example “Pending review” to catch posts submitted for review. Leave empty for any status.', 'agent-studio'), $statuses),
                    self::listField('from', __('Changes from', 'agent-studio'), __('Usually left empty.', 'agent-studio'), $statuses),
                ],
            ],
            [
                'key' => TriggerType::CommentPosted->value,
                'label' => __('A comment is posted', 'agent-studio'),
                'description' => __('When anyone leaves a new comment, including comments waiting for moderation.', 'agent-studio'),
                'group' => $content,
                'fields' => [
                    self::listField('post_types', __('On which kinds of content', 'agent-studio'), __('Leave empty for all kinds.', 'agent-studio'), $postTypes),
                ],
            ],
            [
                'key' => TriggerType::UserRegistered->value,
                'label' => __('Someone joins the site', 'agent-studio'),
                'description' => __('When someone registers, or an administrator adds a user.', 'agent-studio'),
                'group' => __('People', 'agent-studio'),
                'fields' => [
                    self::listField('roles', __('With which roles', 'agent-studio'), __('Leave empty for any role.', 'agent-studio'), self::roles()),
                ],
            ],
            [
                'key' => TriggerType::Schedule->value,
                'label' => __('On a schedule', 'agent-studio'),
                'description' => __('Every hour, day or week at a set time. Good for reports, digests and regular clean-ups.', 'agent-studio'),
                'group' => __('Time', 'agent-studio'),
                'fields' => [['key' => 'schedule', 'type' => 'schedule', 'label' => __('When', 'agent-studio')]],
            ],
            [
                'key' => TriggerType::Webhook->value,
                'label' => __('Another system calls it', 'agent-studio'),
                'description' => __('Gives the agent a private web address (a webhook). Other apps, like Zapier or your own code, call it to start the agent.', 'agent-studio'),
                'group' => __('Other systems', 'agent-studio'),
                'fields' => [['key' => 'webhook', 'type' => 'webhook', 'label' => __('Webhook', 'agent-studio')]],
            ],
        ];

        foreach ($this->definitions?->all() ?? [] as $definition) {
            $entries[] = [
                'key' => self::CUSTOM_PREFIX . $definition->key(),
                'label' => $definition->label(),
                'description' => $definition->description(),
                'group' => self::pluginGroup($definition->key()),
                'fields' => array_map(static fn ($field): array => $field->toArray(), $definition->fields()),
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function byKey(): array
    {
        $byKey = [];
        foreach ($this->all() as $entry) {
            $byKey[$entry['key']] = $entry;
        }

        return $byKey;
    }

    /**
     * Search and page through the catalog.
     *
     * @return array{items: list<array<string, mixed>>, total: int, groups: list<string>}
     */
    public function search(string $term, string $group, int $page, int $perPage): array
    {
        $all = $this->all();
        $groups = array_values(array_unique(array_column($all, 'group')));
        $term = self::lower(trim($term));

        $matches = array_values(array_filter(
            $all,
            static fn (array $entry): bool => ($group === '' || $entry['group'] === $group)
                && ($term === '' || str_contains(self::lower($entry['label'] . ' ' . $entry['description'] . ' ' . $entry['group'] . ' ' . $entry['key']), $term))
        ));

        return [
            'items' => array_slice($matches, ($page - 1) * $perPage, $perPage),
            'total' => count($matches),
            'groups' => $groups,
        ];
    }

    /**
     * The catalog key of a saved trigger.
     */
    public static function keyOf(Trigger $trigger): string
    {
        return $trigger->type === TriggerType::Custom ? self::CUSTOM_PREFIX . $trigger->kind() : $trigger->type->value;
    }

    /**
     * A placeholder entry for a saved trigger whose plugin is no longer active, so it can still be seen and removed.
     *
     * @return array<string, mixed>
     */
    public static function missing(string $key): array
    {
        return [
            'key' => $key,
            'label' => str_starts_with($key, self::CUSTOM_PREFIX) ? substr($key, strlen(self::CUSTOM_PREFIX)) : $key,
            'description' => __('The plugin that adds this event is not active, so it cannot start the agent right now.', 'agent-studio'),
            'group' => __('Not available', 'agent-studio'),
            'fields' => [],
            'missing' => true,
        ];
    }

    /**
     * @param list<array{value: string, label: string}> $suggestions
     *
     * @return array<string, mixed>
     */
    private static function listField(string $key, string $label, string $help, array $suggestions): array
    {
        return ['key' => $key, 'type' => 'list', 'label' => $label, 'help' => $help, 'suggestions' => $suggestions];
    }

    /**
     * "my-shop/order-placed" is grouped under "My shop".
     */
    private static function pluginGroup(string $key): string
    {
        $namespace = str_replace(['-', '_'], ' ', strstr($key, '/', true) ?: $key);

        return ucfirst($namespace);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function postTypes(): array
    {
        $items = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $type) {
            if (! str_starts_with($type->name, 'agent_studio_') && ! str_starts_with($type->name, 'wp_')) {
                $items[] = ['value' => $type->name, 'label' => (string) $type->labels->singular_name];
            }
        }

        return $items;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function postStatuses(): array
    {
        $items = [];
        foreach (get_post_stati(['show_in_admin_status_list' => true], 'objects') as $status) {
            $items[$status->name] = ['value' => $status->name, 'label' => (string) $status->label];
        }
        $items['trash'] ??= ['value' => 'trash', 'label' => __('Trash', 'agent-studio')];

        return array_values($items);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function roles(): array
    {
        $items = [];
        foreach (wp_roles()->get_names() as $role => $name) {
            $items[] = ['value' => (string) $role, 'label' => translate_user_role($name)];
        }

        return $items;
    }

    private static function lower(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }
}
