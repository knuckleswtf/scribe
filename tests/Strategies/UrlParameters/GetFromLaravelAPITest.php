<?php

namespace Knuckles\Scribe\Tests\Strategies\UrlParameters;

use Closure;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Shared\UrlParamsNormalizer;
use Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tools\DocumentationConfig;

class GetFromLaravelAPITest extends BaseLaravelTest
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_infer_type_from_model_binding()
    {
        $endpoint = $this->endpointForRoute("users/{id}", TestController::class, 'withInjectedModel');
        // Can only run on PHP 8.1
        // $endpoint = $this->endpointForRoute("categories/{category}/users/{id}/", TestController::class, 'withInjectedEnumAndModel');
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            "name" => "id",
            "description" => "The ID of the user.",
            "required" => true,
            "type" => "integer",
        ], $results['id']);/*
        $this->assertArraySubset([
            "name" => "category",
            "description" => "The category.",
            "required" => true,
            "type" => "string",
            "example" => \Knuckles\Scribe\Tests\Fixtures\Category::cases()[0]->value,
        ], $results['category']);*/
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
            $e->uri = UrlParamsNormalizer::normalizeParameterNamesInRouteUri($e->route, $e->method);
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

        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        $user = TestUser::create(['name' => 'Bully Maguire', 'id' => 23]);

        $endpoint = $this->endpointForRoute("users/{user:id}", TestController::class, 'withInjectedModel');
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            "name" => "user_id",
            "description" => "The ID of the user.",
            "required" => true,
            "type" => "integer",
            "example" => $user->id,
        ], $results['user_id']);
    }

    /** @test */
    public function can_infer_from_model_even_if_not_bound()
    {
        $oldNamespace = $this->app->getNamespace();
        $reflectedApp = new \ReflectionClass($this->app);
        $property = $reflectedApp->getProperty('namespace');
        $property->setAccessible(true);
        $property->setValue($this->app, "Knuckles\\Scribe\\Tests\\Fixtures\\");

        $endpoint = $this->endpointForRoute("test-users/{id}", TestController::class, 'dummy');
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            "name" => "id",
            "description" => "The ID of the test user.",
            "required" => true,
            "type" => "integer",
        ], $results['id']);

        $property->setValue($this->app, $oldNamespace);
    }

    protected function endpointForRoute($path, $controller, $method): ExtractedEndpointData
    {
        return $this->endpoint(function (ExtractedEndpointData $e) use ($path, $method, $controller) {
            $e->method = new \ReflectionMethod($controller, $method);
            $e->route = app(Router::class)->addRoute(['GET'], $path, ['uses' => [$controller, $method]]);
            $e->uri = UrlParamsNormalizer::normalizeParameterNamesInRouteUri($e->route, $e->method);
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
