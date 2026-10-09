<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Skill;

use InvalidArgumentException;

/**
 * Reads and writes SKILL.md files (YAML front matter with name and description, then Markdown).
 */
final class SkillMarkdown
{
    private const DESCRIPTION_FALLBACK_LENGTH = 200;

    public static function parse(string $markdown): Skill
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;

        $meta = [];
        $body = $text;
        if (preg_match('/\A---\n(.*?)\n---[ \t]*(?:\n(.*))?\z/s', $text, $matches) === 1) {
            $meta = self::frontMatter($matches[1]);
            $body = $matches[2] ?? '';
        }

        $body = trim($body);
        $name = trim($meta['name'] ?? '');
        $slugSource = $name;

        if ($name === '' && preg_match('/^#\s+(.+)$/m', $body, $heading) === 1) {
            $name = trim($heading[1]);
            $slugSource = $name;
        }

        if ($name === '') {
            throw new InvalidArgumentException('The skill needs a name. Add "name:" to the front matter.');
        }

        $description = trim($meta['description'] ?? '');
        if ($description === '') {
            $description = self::firstParagraph($body);
        }

        return new Skill(self::slugify($slugSource), $name, $description, $body);
    }

    /**
     * The skill as a SKILL.md file that parse() reads back unchanged.
     */
    public static function render(Skill $skill): string
    {
        return "---\n"
            . 'name: ' . self::quote($skill->name) . "\n"
            . 'description: ' . self::quote($skill->description) . "\n"
            . "---\n\n"
            . rtrim($skill->body) . "\n";
    }

    public static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return substr(trim($slug, '-'), 0, 64);
    }

    /**
     * Reads simple "key: value" pairs. Indented lines continue the previous value.
     *
     * @return array<string, string>
     */
    private static function frontMatter(string $yaml): array
    {
        $values = [];
        $currentKey = null;

        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^([A-Za-z][\w-]*):[ \t]*(.*)$/', $line, $parts) === 1) {
                $currentKey = strtolower($parts[1]);
                $values[$currentKey] = self::unquote(trim($parts[2]));
                continue;
            }

            if ($currentKey !== null && preg_match('/^[ \t]+\S/', $line) === 1) {
                $values[$currentKey] = trim($values[$currentKey] . ' ' . trim($line));
            }
        }

        // Block scalar markers (">" and "|") carry no value of their own.
        foreach ($values as $key => $value) {
            $values[$key] = trim((string) preg_replace('/^[>|][+-]?\s*/', '', $value));
        }

        return $values;
    }

    /**
     * Double-quotes a one-line value so colons, quotes and leading symbols survive.
     */
    private static function quote(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            if ($first === '"' && substr($value, -1) === '"') {
                return (string) preg_replace('/\\\\(["\\\\])/', '$1', substr($value, 1, -1));
            }
            if ($first === "'" && substr($value, -1) === "'") {
                return str_replace("''", "'", substr($value, 1, -1));
            }
        }

        return $value;
    }

    private static function firstParagraph(string $body): string
    {
        foreach (preg_split('/\n\s*\n/', $body) ?: [] as $block) {
            $block = trim($block);
            if ($block !== '' && $block[0] !== '#') {
                $block = (string) preg_replace('/\s+/', ' ', $block);

                return strlen($block) > self::DESCRIPTION_FALLBACK_LENGTH
                    ? rtrim(substr($block, 0, self::DESCRIPTION_FALLBACK_LENGTH - 1)) . '…'
                    : $block;
            }
        }

        return '';
    }
}
