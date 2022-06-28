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
        if (version_compare($this->app->version(), '7.0.0', '<')) {
            $this->markTestSkipped("Laravel < 7.x doesn't support field binding syntax.");

            return;
        }

        Route::apiResource('things', TestController::class)
            ->only('show')
            ->parameters([
                'things' => 'thing:id',
            ]);
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
            ->only( 'destroy')
            ->parameters([
                'things' => 'thing:id',
                'otherthings' => 'otherthing:id',
            ]);

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
        if (version_compare($this->app->version(), '7.0.0', '<')) {
            $this->markTestSkipped("Laravel < 7.x doesn't support field binding syntax.");

            return;
        }

        Route::apiResource('audio-things', TestController::class)
            ->only('show')
            ->parameters([
                'audio-things' => 'audio_thing:id',
            ]);
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
