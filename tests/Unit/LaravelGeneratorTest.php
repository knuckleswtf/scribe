<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Tests\Fixtures\TestController;

class LaravelGeneratorTest extends GeneratorTestCase
{
    public function createRoute(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        return new Route([$httpMethod], $path, ['uses' => $class . "@$controllerMethod"]);
    }

    public function createRouteUsesArray(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        return new Route([$httpMethod], $path, ['uses' => [$class, $controllerMethod]]);
    }

    public function createRouteUsesCallable(string $httpMethod, string $path, callable $handler, $register = false)
    {
        return new Route([$httpMethod], $path, ['uses' => $handler]);
    }
}
