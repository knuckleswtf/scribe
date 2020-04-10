<?php

namespace Knuckles\Scribe\Extracting\Strategies\RequestHeaders;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use ReflectionClass;
use ReflectionFunctionAbstract;

class GetFromRouteRules extends Strategy
{
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $context = [])
    {
        return $routeRules['headers'] ?? [];
    }
}
