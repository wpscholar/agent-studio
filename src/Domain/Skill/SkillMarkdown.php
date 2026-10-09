<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Skill;

use InvalidArgumentException;

/**
 * Parses a SKILL.md file (YAML front matter with name and description, then Markdown).
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

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            if (($first === '"' || $first === "'") && substr($value, -1) === $first) {
                return substr($value, 1, -1);
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
