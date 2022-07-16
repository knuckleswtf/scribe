<?php

namespace Knuckles\Scribe\Tests\Strategies\UrlParameters;

use Closure;
use Illuminate\Routing\Router;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI;
use Knuckles\Scribe\Extracting\UrlParamsNormalizer;
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
        $endpoint = $this->endpointForRoute("users/{id}", TestController::class, 'withInjectedModel');
        $results = $this->fetch($endpoint);

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
        $endpoint = $this->endpointForRoute("everything/{cat_id}", TestController::class, 'dummy');
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            "name" => "cat_id",
            "description" => "The ID of the cat.",
            "required" => true,
            "type" => "string",
        ], $results['cat_id']);

        $endpoint->route = app(Router::class)->addRoute(['GET'], 'dogs/{id}', ['uses' => [TestController::class, 'dummy']]);;
        $endpoint->uri = $endpoint->route->uri;
        $results = $this->fetch($endpoint);

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
        $regex = '/catz\d+-\d/';
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) use ($regex) {
            $e->method = new \ReflectionMethod(TestController::class, 'dummy');
            $e->route = app(Router::class)->addRoute(['GET'], "everything/{cat_id}", ['uses' => [TestController::class, 'dummy']])
                ->where('cat_id', $regex);
            $e->uri = $e->route->uri;
        });
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            "name" => "cat_id",
            "description" => "The ID of the cat.",
            "required" => true,
            "type" => "string",
        ], $results['cat_id']);
        $this->assertMatchesRegularExpression($regex, $results['cat_id']['example']);
    }

    /** @test */
    public function can_infer_data_from_field_bindings()
    {
        $endpoint = $this->endpointForRoute("audio/{audio:slug}", TestController::class, 'dummy');
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            "name" => "audio_slug",
            "description" => "The slug of the audio.",
            "required" => true,
            "type" => "string",
        ], $results['audio_slug']);

        $endpoint = $this->endpointForRoute("users/{user:id}", TestController::class, 'withInjectedModel');
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            "name" => "user_id",
            "description" => "The ID of the user.",
            "required" => true,
            "type" => "integer",
        ], $results['user_id']);
    }

    protected function endpointForRoute($path, $controller, $method): ExtractedEndpointData
    {
        return $this->endpoint(function (ExtractedEndpointData $e) use ($path, $method, $controller) {
            $e->method = new \ReflectionMethod($controller, $method);
            $e->route = app(Router::class)->addRoute(['GET'], $path, ['uses' => [$controller, $method]]);
            $e->uri = $e->route->uri;
        });
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

    protected function fetch($endpoint): array
    {
        $strategy = new GetFromLaravelAPI(new DocumentationConfig([]));
        return $strategy($endpoint, []);
    }

}
