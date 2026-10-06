<?php

declare(strict_types=1);

namespace WPAgents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAgents\Domain\Ability\SchemaNormalizer;

final class SchemaNormalizerTest extends TestCase
{
    public function testNoSchemaMeansNoInput(): void
    {
        $this->assertSame(['schema' => [], 'wrapped' => false], SchemaNormalizer::forModel([]));
    }

    public function testObjectSchemasPassThrough(): void
    {
        $schema = ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];

        $this->assertSame(['schema' => $schema, 'wrapped' => false], SchemaNormalizer::forModel($schema));
    }

    public function testBooleanRequiredOnPropertiesBecomesARequiredList(): void
    {
        $result = SchemaNormalizer::forModel([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'required' => true],
                'note' => ['type' => 'string', 'required' => false],
            ],
        ]);

        $this->assertSame(['id'], $result['schema']['required']);
        $this->assertFalse(isset($result['schema']['properties']['id']['required']));
        $this->assertFalse(isset($result['schema']['properties']['note']['required']));
    }

    public function testNonObjectRootIsWrapped(): void
    {
        $result = SchemaNormalizer::forModel(['type' => 'string', 'minLength' => 10, 'required' => true]);

        $this->assertTrue($result['wrapped']);
        $this->assertSame('object', $result['schema']['type']);
        $this->assertSame(['input'], $result['schema']['required']);
        $this->assertSame('string', $result['schema']['properties']['input']['type']);
        $this->assertFalse(isset($result['schema']['properties']['input']['required']));
    }

    public function testNestedItemsAreCleaned(): void
    {
        $result = SchemaNormalizer::forModel([
            'type' => 'object',
            'properties' => [
                'tags' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['n' => ['type' => 'string', 'required' => true]]]],
            ],
        ]);

        $this->assertSame(['n'], $result['schema']['properties']['tags']['items']['required']);
    }
}
