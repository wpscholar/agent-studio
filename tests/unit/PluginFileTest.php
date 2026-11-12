<?php

declare(strict_types=1);

namespace AgentStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The plugin header drives WordPress's own compatibility checks, so keep it honest.
 */
final class PluginFileTest extends TestCase
{
    public function testHeaderDeclaresRequirements(): void
    {
        $header = (string) file_get_contents(dirname(__DIR__, 2) . '/agent-studio.php');

        $this->assertStringContainsString('Plugin Name:       Agent Studio', $header);
        $this->assertMatchesRegularExpression('/Requires PHP:\s+8\.2/', $header);
        $this->assertMatchesRegularExpression('/Requires at least:\s+7\.0/', $header);
        $this->assertStringContainsString('Text Domain:       agent-studio', $header);
    }

    public function testHeaderSendsUpdateChecksToGitHub(): void
    {
        $header = (string) file_get_contents(dirname(__DIR__, 2) . '/agent-studio.php');

        // WordPress turns the Update URI's host into the update_plugins_github.com filter.
        $this->assertMatchesRegularExpression('#Update URI:\s+https://github\.com/wpscholar/agent-studio\n#', $header);
    }

    public function testVersionIsTheSameEverywhere(): void
    {
        $root = dirname(__DIR__, 2);
        $php = (string) file_get_contents($root . '/agent-studio.php');
        $package = json_decode((string) file_get_contents($root . '/package.json'), true);

        preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $php, $header);
        preg_match("/define\\('AGENT_STUDIO_VERSION', '([^']+)'\\)/", $php, $constant);

        $this->assertNotEmpty($header[1] ?? '');
        $this->assertSame($header[1], $constant[1] ?? null, 'AGENT_STUDIO_VERSION must match the header Version.');
        $this->assertSame($header[1], $package['version'] ?? null, 'package.json version names the release zip.');
    }
}
