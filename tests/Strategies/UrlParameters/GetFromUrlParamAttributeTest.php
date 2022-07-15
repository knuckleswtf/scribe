<?php

namespace Knuckles\Scribe\Tests\Strategies\UrlParameters;

use Closure;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\UrlParam;
use Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamAttribute;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ReflectionClass;
use ReflectionFunction;

class GetFromUrlParamAttributeTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_urlparam_attribute()
    {
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(UrlParamAttributeTestController::class);
            $e->method = $e->controller->getMethod('methodWithAttributes');
        });
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            'id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'The id of the order.',
            ],
            'lang' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Will be inherited.',
            ],
            'withType' => [
                'type' => 'number',
                'required' => false,
                'description' => 'With type, maybe.',
            ],
            'withTypeDefinitely' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'With type.',
            ],
            'barebones' => [
                'type' => 'string',
                'required' => true,
                'description' => '',
            ],
            'barebonesType' => [
                'type' => 'number',
                'required' => true,
                'description' => '',
            ],
            'barebonesOptional' => [
                'type' => 'string',
                'required' => false,
                'description' => '',
            ],
            'withExampleOnly' => [
                'type' => 'string',
                'required' => true,
                'description' => '',
                'example' => '12',
            ],
            'withExampleOnlyButTyped' => [
                'type' => 'integer',
                'required' => true,
                'description' => '',
                'example' => 12
            ],
            'noExampleNoDescription' => [
                'type' => 'string',
                'required' => true,
                'description' => '',
                'example' => null
            ],
            'noExample' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Something',
                'example' => null
            ],
        ], $results);
    }

    /** @test */
    public function can_fetch_from_urlparam_attribute_on_closure()
    {
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = null;
            $e->method = new ReflectionFunction('\Knuckles\Scribe\Tests\Strategies\UrlParameters\functionWithAttributes');
        });
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            'a_parameter' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Described',
                'example' => 'en',
            ],
        ], $results);
    }

    protected function fetch($endpoint): array
    {
        $strategy = new GetFromUrlParamAttribute(new DocumentationConfig([]));
        return $strategy($endpoint, []);
    }

    protected function endpoint(Closure $configure): ExtractedEndpointData
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = []) {}
        };
        $configure($endpoint);
        return $endpoint;
    }

}


#[UrlParam("id", description: "Will be overriden.")]
#[UrlParam("lang", description: "Will be inherited.", required: false)]
class UrlParamAttributeTestController
{
    #[UrlParam("id", description: "The id of the order.")]
    #[UrlParam("withType", required: false, type: "number", description: "With type, maybe.")]
    #[UrlParam("withTypeDefinitely", "integer", "With type.")]
    #[UrlParam("barebones")]
    #[UrlParam("barebonesType", type: "number")]
    #[UrlParam("barebonesOptional", required: false)]
    #[UrlParam("withExampleOnly", example: "12")]
    #[UrlParam("withExampleOnlyButTyped", type: "int", example: 12)]
    #[UrlParam("noExampleNoDescription", example: "No-example.")]
    #[UrlParam("noExample", description: "Something", example: "No-example")]
    public function methodWithAttributes()
    {

    }
}

#[UrlParam("a_parameter", required: false, type: "string", description: "Described", example: "en")]
function functionWithAttributes()
{

}
