<?php

declare(strict_types=1);

namespace AgentStudio\Domain\Update;

/**
 * A published version of the plugin and the zip that installs it.
 */
final class Release
{
    public function __construct(
        public readonly string $version,
        public readonly string $packageUrl,
        public readonly string $pageUrl,
        public readonly string $notes = '',
        public readonly string $publishedAt = '',
    ) {
    }

    public function isNewerThan(string $installedVersion): bool
    {
        return version_compare($this->version, $installedVersion, '>');
    }

    /**
     * @return array{version: string, packageUrl: string, pageUrl: string, notes: string, publishedAt: string}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'packageUrl' => $this->packageUrl,
            'pageUrl' => $this->pageUrl,
            'notes' => $this->notes,
            'publishedAt' => $this->publishedAt,
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        foreach (['version', 'packageUrl', 'pageUrl'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key]) || $data[$key] === '') {
                return null;
            }
        }

        return new self(
            $data['version'],
            $data['packageUrl'],
            $data['pageUrl'],
            is_string($data['notes'] ?? null) ? $data['notes'] : '',
            is_string($data['publishedAt'] ?? null) ? $data['publishedAt'] : '',
        );
    }
}
