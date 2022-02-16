<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Support\Facades\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Matching\RouteMatcher;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;

class ExtractedEndpointDataTest extends BaseLaravelTest
{
    /** @test */
    public function will_normalize_resource_url_params()
    {
        Route::apiResource('things', TestController::class)
            ->only('show');
        $routeRules[0]['match'] = ['prefixes' => '*', 'domains' => '*'];

        $matcher = new RouteMatcher();
        $matchedRoutes = $matcher->getRoutes($routeRules);

        foreach ($matchedRoutes as $matchedRoute) {
            $route = $matchedRoute->getRoute();
            $this->assertEquals('things/{thing}', $route->uri);
            $endpoint = new ExtractedEndpointData([
                'route' => $route,
                'uri' => $route->uri,
                'httpMethods' => $route->methods,
            ]);
            $this->assertEquals('things/{id}', $endpoint->uri);
        }

        Route::apiResource('things.otherthings', TestController::class)
            ->only( 'destroy');

        $routeRules[0]['match'] = ['prefixes' => '*/otherthings/*', 'domains' => '*'];
        $matchedRoutes = $matcher->getRoutes($routeRules);
        foreach ($matchedRoutes as $matchedRoute) {
            $route = $matchedRoute->getRoute();
            $this->assertEquals('things/{thing}/otherthings/{otherthing}', $route->uri);
            $endpoint = new ExtractedEndpointData([
                'route' => $route,
                'uri' => $route->uri,
                'httpMethods' => $route->methods,
            ]);
            $this->assertEquals('things/{thing_id}/otherthings/{id}', $endpoint->uri);
        }
    }

    /** @test */
    public function will_normalize_resource_url_params_with_hyphens()
    {
        Route::apiResource('audio-things', TestController::class)
            ->only('show');
        $routeRules[0]['match'] = ['prefixes' => '*', 'domains' => '*'];

        $matcher = new RouteMatcher();
        $matchedRoutes = $matcher->getRoutes($routeRules);

        foreach ($matchedRoutes as $matchedRoute) {
            $route = $matchedRoute->getRoute();
            $this->assertEquals('audio-things/{audio_thing}', $route->uri);
            $endpoint = new ExtractedEndpointData([
                'route' => $route,
                'uri' => $route->uri,
                'httpMethods' => $route->methods,
            ]);
            $this->assertEquals('audio-things/{id}', $endpoint->uri);
        }
    }
}
