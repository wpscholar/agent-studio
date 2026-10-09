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
}
