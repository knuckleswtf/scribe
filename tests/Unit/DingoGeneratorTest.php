<?php

namespace Knuckles\Scribe\Tests\Unit;

use Dingo\Api\Routing\RouteCollection;
use Dingo\Api\Routing\Router;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\Generator;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;

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

        $config = $this->config;
        $config['router'] = 'dingo';
        $this->generator = new Generator(new DocumentationConfig($config));
    }

    public function createRoute(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        $desiredRoute = null;
        /** @var Router $api */
        $api = app(Router::class);
        $api->version('v1', function (Router $api) use ($class, $controllerMethod, $path, $httpMethod, &$desiredRoute) {
            $desiredRoute = $api->$httpMethod($path, $class . "@$controllerMethod");
        });
        $routes = app(\Dingo\Api\Routing\Router::class)->getRoutes('v1');

        /*
         * Doing this bc we want an instance of Dingo\Api\Routing\Route, not Illuminate\Routing\Route, which the method above returns
         */
        return collect($routes)
            ->first(function (Route $route) use ($desiredRoute) {
                return $route->uri() === $desiredRoute->uri();
            });
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
