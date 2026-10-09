<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Setup;

use InvalidArgumentException;

/**
 * One field on a setup screen, described as data so plugins can add their own
 * connection and channel types and our screens draw the form for them.
 *
 * Secret fields are stored in the secret store and never sent back to the
 * browser; the screen shows that a value is saved and leaves the box empty.
 */
final class SetupField
{
    public const TEXT = 'text';
    public const URL = 'url';
    public const SECRET = 'secret';
    public const TEXTAREA = 'textarea';
    public const SELECT = 'select';
    public const TOGGLE = 'toggle';

    public const TYPES = [self::TEXT, self::URL, self::SECRET, self::TEXTAREA, self::SELECT, self::TOGGLE];

    /**
     * @param array<string, string> $options     For select fields: value => label.
     * @param array<string, string> $showWhen    Show only when other fields have these values.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = self::TEXT,
        public readonly string $help = '',
        public readonly bool $required = false,
        public readonly string $placeholder = '',
        public readonly array $options = [],
        public readonly string $default = '',
        public readonly array $showWhen = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1) {
            throw new InvalidArgumentException('A setup field key uses lowercase letters, numbers and underscores.');
        }
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown setup field type "%s".', $type));
        }
    }

    public function isSecret(): bool
    {
        return $this->type === self::SECRET;
    }

    /**
     * Cleans a submitted value for this field. Returns the default for values of the wrong shape.
     */
    public function clean(mixed $value): string
    {
        if ($this->type === self::TOGGLE) {
            return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true) ? '1' : '';
        }

        if (! is_scalar($value)) {
            return $this->default;
        }

        $value = (string) $value;
        if ($this->type === self::TEXTAREA) {
            return trim(str_replace("\0", '', $value));
        }

        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $value));
        if ($this->type === self::SELECT && $this->options !== [] && ! array_key_exists($value, $this->options)) {
            return $this->default;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'help' => $this->help,
            'required' => $this->required,
            'placeholder' => $this->placeholder,
            'options' => $this->options,
            'default' => $this->default,
            'showWhen' => $this->showWhen,
        ];
    }
}
