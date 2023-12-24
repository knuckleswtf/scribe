<?php

namespace Knuckles\Scribe\Tools;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class RoutePatternMatcher
{
    public static function matches(Route $route, $patterns): bool
    {
        $routeName = $route->getName();
        $routePathWithoutInitialSlash = $route->uri();
        $routePathWithInitialSlash = "/$routePathWithoutInitialSlash";
        $routeMethods = $route->methods();
        if (Str::is($patterns, $routeName)
            || Str::is($patterns, $routePathWithoutInitialSlash)
            || Str::is($patterns, $routePathWithInitialSlash)) {
            return true;
        }

        foreach ($routeMethods as $httpMethod) {
            if (Str::is($patterns, "$httpMethod $routePathWithoutInitialSlash")
                || Str::is($patterns, "$httpMethod $routePathWithInitialSlash")) {
                return true;
            }
        }

        return false;
    }
}
