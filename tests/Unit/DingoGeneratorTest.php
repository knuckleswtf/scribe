<?php

namespace Knuckles\Scribe\Tests\Unit;

use Dingo\Api\Routing\Router;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\Fixtures\TestController;

/**
 * @group dingo
 */
class DingoGeneratorTest extends GeneratorTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ScribeServiceProvider::class,
            \Dingo\Api\Provider\LaravelServiceProvider::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        config(['scribe.router' => 'dingo']);
    }

    public function createRoute(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        $route = null;
        /** @var Router $api */
        $api = app(Router::class);
        $api->version('v1', function (Router $api) use ($class, $controllerMethod, $path, $httpMethod, &$route) {
            $route = $api->$httpMethod($path, $class . "@$controllerMethod");
        });

        return $route;
    }

    public function createRouteUsesArray(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        $route = null;
        /** @var Router $api */
        $api = app(Router::class);
        $api->version('v1', function (Router $api) use ($class, $controllerMethod, $path, $httpMethod, &$route) {
            $route = $api->$httpMethod($path, [$class, $controllerMethod]);
        });

        return $route;
    }

    public function createRouteUsesCallable(string $httpMethod, string $path, callable $handler, $register = false)
    {
        $route = null;
        /** @var Router $api */
        $api = app(Router::class);
        $api->version('v1', function (Router $api) use ($handler, $path, $httpMethod, &$route) {
            $route = $api->$httpMethod($path, $handler);
        });

        return $route;
    }
}
