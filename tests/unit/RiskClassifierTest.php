<?php

declare(strict_types=1);

namespace WPAgents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAgents\Domain\Ability\Risk;
use WPAgents\Domain\Ability\RiskClassifier;

final class RiskClassifierTest extends TestCase
{
    public function testReadonlyAnnotationMeansRead(): void
    {
        $this->assertSame(Risk::Read, RiskClassifier::fromMeta(['annotations' => ['readonly' => true]]));
    }

    public function testDestructiveAnnotation(): void
    {
        $this->assertSame(
            Risk::Destructive,
            RiskClassifier::fromMeta(['annotations' => ['readonly' => false, 'destructive' => true]])
        );
    }

    public function testUnknownMeansWrite(): void
    {
        $this->assertSame(Risk::Write, RiskClassifier::fromMeta([]));
        $this->assertSame(Risk::Write, RiskClassifier::fromMeta(['annotations' => ['readonly' => null, 'destructive' => null]]));
        $this->assertSame(Risk::Write, RiskClassifier::fromMeta(['annotations' => 'nonsense']));
    }

    public function testExplicitRiskWinsOverAnnotations(): void
    {
        $meta = ['wpa' => ['risk' => 'external'], 'annotations' => ['readonly' => true]];

        $this->assertSame(Risk::External, RiskClassifier::fromMeta($meta));
    }

    public function testInvalidExplicitRiskIsIgnored(): void
    {
        $meta = ['wpa' => ['risk' => 'harmless'], 'annotations' => ['destructive' => true]];

        $this->assertSame(Risk::Destructive, RiskClassifier::fromMeta($meta));
    }
}
