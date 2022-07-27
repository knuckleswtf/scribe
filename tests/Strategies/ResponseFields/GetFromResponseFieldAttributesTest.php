<?php

namespace Knuckles\Scribe\Tests\Strategies\ResponseFields;

use Closure;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldAttribute;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ReflectionClass;

class GetFromResponseFieldAttributesTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_responsefield_attribute()
    {
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(ResponseFieldAttributeTestController::class);
            $e->method = $e->controller->getMethod('methodWithAttributes');
            $e->responses = new ResponseCollection([
                [
                    'status' => 400,
                    'content' => json_encode(['id' => 6.4]),
                ],
                [
                    'status' => 200,
                    'content' => json_encode(['id' => 6]),
                ],
                [
                    'status' => 201,
                    'content' => json_encode(['id' => 'haha']),
                ],
            ]);
        });
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            'id' => [
                'type' => 'integer',
                'description' => 'The id of the newly created user.',
            ],
            'other' => [
                'type' => 'string',
                'description' => '',
            ],
        ], $results);
    }

    protected function fetch($endpoint): array
    {
        $strategy = new GetFromResponseFieldAttribute(new DocumentationConfig([]));
        return $strategy($endpoint);
    }

    protected function endpoint(Closure $configure): ExtractedEndpointData
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
            }
        };
        $configure($endpoint);
        return $endpoint;
    }
}

class ResponseFieldAttributeTestController
{
    #[ResponseField('id', description: 'The id of the newly created user.')]
    #[ResponseField('other', 'string')]
    public function methodWithAttributes()
    {
    }
}
