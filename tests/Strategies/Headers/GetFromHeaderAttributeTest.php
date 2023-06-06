<?php

namespace Knuckles\Scribe\Tests\Strategies\Headers;

use Attribute;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderAttribute;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ReflectionClass;

class GetFromHeaderAttributeTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_header_attribute()
    {
        $results = $this->getHeaderFromAttribute('methodWithAttributes');

        $this->assertArraySubset([
            'Api-Version' => 'v1',
        ], $results);
        $this->assertArrayHasKey('Some-Custom', $results);
        $this->assertNotEmpty($results['Some-Custom']);
    }

    /** @test */
    public function can_fetch_child_of_header_attribute()
    {
        $results = $this->getHeaderFromAttribute('methodWithCustomHeaderAttribute');

        $this->assertArraySubset([
            'Api-Version' => 'v1',
        ], $results);
        $this->assertArrayHasKey('hello', $results);
        $this->assertEquals('world', $results['hello']);
    }

    private function getHeaderFromAttribute(string $methodName): array
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = []) {}
        };
        $endpoint->controller = new ReflectionClass(\Knuckles\Scribe\Tests\Strategies\Headers\HeaderAttributeTestController::class);
        $endpoint->method = $endpoint->controller->getMethod($methodName);

        $strategy = new GetFromHeaderAttribute(new DocumentationConfig([]));

        return $strategy($endpoint);
    }

}

#[Header("Api-Version", "v1")]
class HeaderAttributeTestController
{
    #[Header("Some-Custom")]
    public function methodWithAttributes()
    {

    }

    #[CustomHeaderClass()]
    public function methodWithCustomHeaderAttribute()
    {

    }
}

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class CustomHeaderClass extends Header
{
    public function __construct()
    {
        parent::__construct('hello', 'world');
    }
}
