<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Setup;

/**
 * One numbered step of a setup: what to do, in plain words, and any fields to fill in.
 */
final class SetupStep
{
    public const ACTION_AUTHORIZE = 'authorize';

    /**
     * @param list<string>                              $instructions Short paragraphs. URLs in them become links.
     * @param list<SetupField>                          $fields
     * @param list<array{label: string, url: string}>   $links        Buttons that open a page, for example "Create the app".
     * @param string                                    $action       A button Agent Studio handles: "authorize" connects the channel by signing in.
     * @param string                                    $actionLabel  The text on that button.
     * @param bool                                      $done         The step is already complete; the screen shows it ticked.
     */
    public function __construct(
        public readonly string $title,
        public readonly array $instructions = [],
        public readonly array $fields = [],
        public readonly array $links = [],
        public readonly string $action = '',
        public readonly string $actionLabel = '',
        public readonly bool $done = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'instructions' => $this->instructions,
            'fields' => array_map(static fn (SetupField $field): array => $field->toArray(), $this->fields),
            'links' => $this->links,
            'action' => $this->action,
            'actionLabel' => $this->actionLabel,
            'done' => $this->done,
        ];
    }
}
