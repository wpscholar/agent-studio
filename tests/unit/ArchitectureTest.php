<?php

declare(strict_types=1);

namespace WPAgents\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Enforces the layering rule from the standards: Domain, Contracts and
 * Application never touch WordPress.
 */
final class ArchitectureTest extends TestCase
{
    private const FORBIDDEN = '/\b(?:WP_[A-Z][A-Za-z_]*|wpdb'
        . '|(?:add_action|add_filter|apply_filters|do_action|get_option|update_option|get_post_meta|update_post_meta'
        . '|current_user_can|wp_[a-z_]+|esc_[a-z_]+|sanitize_[a-z_]+|__|_e|_x)\s*\()/';

    /**
     * @return array<string, array{0: string}>
     */
    public static function pureLayers(): array
    {
        return [
            'Domain' => ['Domain'],
            'Contracts' => ['Contracts'],
            'Application' => ['Application'],
        ];
    }

    #[DataProvider('pureLayers')]
    public function testLayerDoesNotUseWordPress(string $layer): void
    {
        $violations = [];
        $checked = 0;
        foreach (self::phpFiles(dirname(__DIR__, 2) . '/src/' . $layer) as $path) {
            $checked++;
            $code = (string) file_get_contents($path);
            // Ignore comments, which may legitimately mention WordPress.
            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $code);

            if (preg_match_all(self::FORBIDDEN, $code, $matches) > 0) {
                $violations[] = basename($path) . ': ' . implode(', ', array_unique($matches[0]));
            }
        }

        $this->assertGreaterThan(0, $checked, 'No files were checked in ' . $layer);
        $this->assertSame([], $violations, $layer . ' must not depend on WordPress.');
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $directory): array
    {
        $files = [];
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                array_push($files, ...self::phpFiles($path));
            } elseif (str_ends_with($entry, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
