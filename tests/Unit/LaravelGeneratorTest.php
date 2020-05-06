<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\Fixtures\TestController;

class LaravelGeneratorTest extends GeneratorTestCase
{
    protected function getPackageProviders($app)
    {
        $providers = [
            ScribeServiceProvider::class,
        ];
        if (class_exists(\Dingo\Api\Provider\LaravelServiceProvider::class)) {
            $providers[] = \Dingo\Api\Provider\LaravelServiceProvider::class;
        }
        return $providers;
    }

    public function createRoute(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        if ($register) {
            return RouteFacade::{$httpMethod}($path, $class . "@$controllerMethod");
        } else {
            return new Route([$httpMethod], $path, ['uses' => $class . "@$controllerMethod"]);
        }
    }

    public function createRouteUsesArray(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        if ($register) {
            return RouteFacade::{$httpMethod}($path, [$class . "$controllerMethod"]);
        } else {
            return new Route([$httpMethod], $path, ['uses' => [$class, $controllerMethod]]);
        }
    }

    public function createRouteUsesCallable(string $httpMethod, string $path, callable $handler, $register = false)
    {
        if ($register) {
            return RouteFacade::{$httpMethod}($path, $handler);
        } else {
            return new Route([$httpMethod], $path, ['uses' => $handler]);
        }
    }
}
