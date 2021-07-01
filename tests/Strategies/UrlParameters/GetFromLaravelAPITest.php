<?php

namespace Knuckles\Scribe\Tests\Strategies\UrlParameters;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
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
                $this->method = new \ReflectionMethod(TestController::class, 'withInjectedModel');
                $this->route = app(Router::class)->addRoute(['GET'], "users/{id}", ['uses' => [TestController::class, 'withInjectedModel']]);
                $this->uri = $this->route->uri;
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
    public function can_infer_description_from_url()
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->method = new \ReflectionMethod(TestController::class, 'dummy');
                $this->route = app(Router::class)->addRoute(['GET'], "everything/{cat_id}", ['uses' => [TestController::class, 'dummy']]);
                $this->uri = $this->route->uri;
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

        $endpoint->route = app(Router::class)->addRoute(['GET'], 'dogs/{id}', ['uses' => [TestController::class, 'dummy']]);;
        $endpoint->uri = $endpoint->route->uri;
        $results = $strategy($endpoint, []);

        $this->assertArraySubset([
            "name" => "id",
            "description" => "The ID of the dog.",
            "required" => true,
            "type" => "string",
        ], $results['id']);
    }

    /** @test */
    public function can_infer_example_from_wheres()
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->method = new \ReflectionMethod(TestController::class, 'dummy');

                $route =  app(Router::class)->addRoute(['GET'], "everything/{cat_id}", ['uses' => [TestController::class, 'dummy']]);
                $this->regex = '/catz\d+-\d/';
                $this->route = $route->where('cat_id', $this->regex);
                $this->uri = $this->route->uri;
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
        $this->assertMatchesRegularExpression($endpoint->regex, $results['cat_id']['example']);
    }

    /** @test */
    public function can_infer_data_from_field_bindings()
    {
        if (version_compare($this->app->version(), '7.0.0', '<')) {
            $this->markTestSkipped("Laravel < 7.x doesn't support field binding syntax.");

            return;
        }

        $strategy = new GetFromLaravelAPI(new DocumentationConfig([]));

        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->method = new \ReflectionMethod(TestController::class, 'dummy');

                $route = app(Router::class)->addRoute(['GET'], "audio/{audio:slug}", ['uses' => [TestController::class, 'dummy']]);
                $this->route = $route;
                $this->uri = $route->uri;
            }
        };

        $results = $strategy($endpoint, []);

        $this->assertArraySubset([
            "name" => "audio",
            "description" => "The slug of the audio.",
            "required" => true,
            "type" => "string",
        ], $results['audio']);

        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->method = new \ReflectionMethod(TestController::class, 'withInjectedModel');

                $route = app(Router::class)->addRoute(['GET'], "users/{user:id}", ['uses' => [TestController::class, 'withInjectedModel']]);
                $this->route = $route;
                $this->uri = $route->uri;
            }
        };

        $results = $strategy($endpoint, []);

        $this->assertArraySubset([
            "name" => "user",
            "description" => "The ID of the user.",
            "required" => true,
            "type" => "integer",
        ], $results['user']);
    }
}
