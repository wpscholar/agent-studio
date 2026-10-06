<?php

declare(strict_types=1);

namespace WPAgents\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPAgents\Domain\Skill\Skill;
use WPAgents\Domain\Skill\SkillMarkdown;

final class SkillMarkdownTest extends TestCase
{
    public function testParsesFrontMatterAndBody(): void
    {
        $skill = SkillMarkdown::parse(
            "---\nname: Brand Voice\ndescription: How we write for customers.\n---\n# Brand voice\n\nBe warm and direct.\n"
        );

        $this->assertSame('brand-voice', $skill->slug);
        $this->assertSame('Brand Voice', $skill->name);
        $this->assertSame('How we write for customers.', $skill->description);
        $this->assertStringContainsString('Be warm and direct.', $skill->body);
        $this->assertStringNotContainsString('description:', $skill->body);
    }

    public function testQuotedAndFoldedValues(): void
    {
        $skill = SkillMarkdown::parse(
            "---\nname: \"refund-policy\"\ndescription: >\n  When a customer asks\n  for a refund.\n---\nBody"
        );

        $this->assertSame('refund-policy', $skill->slug);
        $this->assertSame('When a customer asks for a refund.', $skill->description);
    }

    public function testHandlesWindowsLineEndingsAndBom(): void
    {
        $skill = SkillMarkdown::parse("\xEF\xBB\xBF---\r\nname: tone\r\ndescription: Tone.\r\n---\r\nText");

        $this->assertSame('tone', $skill->slug);
        $this->assertSame('Text', $skill->body);
    }

    public function testFallsBackToHeadingAndFirstParagraph(): void
    {
        $skill = SkillMarkdown::parse("# Escalation Rules\n\nEscalate anything legal to a human.\n\nMore detail.");

        $this->assertSame('escalation-rules', $skill->slug);
        $this->assertSame('Escalate anything legal to a human.', $skill->description);
    }

    public function testRejectsAFileWithNoName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SkillMarkdown::parse("Just some text without a heading.");
    }

    public function testSlugRulesAreEnforced(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Skill('Not A Slug', 'x', 'y', 'z');
    }

    public function testSlugify(): void
    {
        $this->assertSame('hello-world-2', SkillMarkdown::slugify('  Hello, World! 2 '));
    }
}
