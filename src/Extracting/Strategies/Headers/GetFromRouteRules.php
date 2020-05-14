<?php

namespace Knuckles\Scribe\Extracting\Strategies\Headers;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use ReflectionClass;
use ReflectionFunctionAbstract;

class GetFromRouteRules extends Strategy
{
    public $stage = 'headers';

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        return $routeRules['headers'] ?? [];
    }
}
