<?php

namespace Knuckles\Scribe\Tests\Strategies\Headers;

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
        $endpoint = new class () extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
            }
        };
        $endpoint->controller = new ReflectionClass(\Knuckles\Scribe\Tests\Strategies\Headers\HeaderAttributeTestController::class);
        $endpoint->method = $endpoint->controller->getMethod('methodWithAttributes');

        $strategy = new GetFromHeaderAttribute(new DocumentationConfig([]));
        $results = $strategy($endpoint);

        $this->assertArraySubset([
            'Api-Version' => 'v1',
        ], $results);
        $this->assertArrayHasKey('Some-Custom', $results);
        $this->assertNotEmpty($results['Some-Custom']);
    }
}

#[Header("Api-Version", "v1")]
class HeaderAttributeTestController
{
    #[Header("Some-Custom")]
    public function methodWithAttributes()
    {
    }
}
