<?php

namespace Knuckles\Scribe\Matching;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Knuckles\Scribe\Tools\RoutePatternMatcher;

class RouteMatcher implements RouteMatcherInterface
{
    public function getRoutes(array $routeRules = []): array
    {
        return $this->getRoutesToBeDocumented($routeRules);
    }

    private function getRoutesToBeDocumented(array $routeRules): array
    {
        $allRoutes = $this->getAllRoutes();

        $matchedRoutes = [];

        foreach ($routeRules as $routeRule) {
            $includes = $routeRule['include'] ?? [];

            foreach ($allRoutes as $route) {
                if ($this->shouldExcludeRoute($route, $routeRule)) {
                    continue;
                }

                if ($this->shouldIncludeRoute($route, $routeRule, $includes)) {
                    $matchedRoutes[] = new MatchedRoute($route, $routeRule['apply'] ?? []);
                }
            }
        }

        return $matchedRoutes;
    }

    private function getAllRoutes()
    {
        return RouteFacade::getRoutes();
    }

    private function shouldIncludeRoute(Route $route, array $routeRule, array $mustIncludes): bool
    {
        if (RoutePatternMatcher::matches($route, $mustIncludes)) {
            return true;
        }

        $domainsToMatch = $routeRule['match']['domains'] ?? [];
        $pathsToMatch = $routeRule['match']['prefixes'] ?? [];

        return Str::is($domainsToMatch, $route->getDomain()) && Str::is($pathsToMatch, $route->uri());
    }

    private function shouldExcludeRoute(Route $route, array $routeRule): bool
    {
        $excludes = $routeRule['exclude'] ?? [];

        // Exclude this package's routes
        $excludes[] = 'scribe';
        $excludes[] = 'scribe.*';

        // Exclude Laravel Telescope routes
        if (class_exists('Laravel\Telescope\Telescope')) {
            $excludes[] = 'telescope/*';
        }

        return RoutePatternMatcher::matches($route, $excludes);
    }
}
