<?php

namespace Knuckles\Scribe\Tests\Strategies\UrlParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class GetFromLaravelAPITest extends BaseLaravelTest
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_url()
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->uri = 'users/{id}';
                $this->method = new \ReflectionMethod(TestController::class, 'withInjectedModel');
            }
        };

        $strategy = new GetFromLaravelAPI(new DocumentationConfig([]));
        $results = $strategy($endpoint, []);

        $this->assertArraySubset([
            "name" => "id",
            "description" => "The ID of the user.",
            "required" => true,
            "type" => "integer",
        ], $results['id']);
        $this->assertIsInt($results['id']['example']);
    }

    /** @test */
    public function can_infer_description()
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->uri = 'everything/{cat_id}';
                $this->method = new \ReflectionMethod(TestController::class, 'dummy');
            }
        };

        $strategy = new GetFromLaravelAPI(new DocumentationConfig([]));
        $results = $strategy($endpoint, []);

        $this->assertArraySubset([
            "name" => "cat_id",
            "description" => "The ID of the cat.",
            "required" => true,
            "type" => "string",
        ], $results['cat_id']);

        $endpoint->uri = 'dogs/{id}';
        $results = $strategy($endpoint, []);

        $this->assertArraySubset([
            "name" => "id",
            "description" => "The ID of the dog.",
            "required" => true,
            "type" => "string",
        ], $results['id']);
    }
}
