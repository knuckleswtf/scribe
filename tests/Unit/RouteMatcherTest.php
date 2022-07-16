<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Knuckles\Scribe\Matching\RouteMatcher;
use Knuckles\Scribe\Tests\BaseLaravelTest;

class RouteMatcherTest extends BaseLaravelTest
{
    /** @test */
    public function respects_domains_rule_for_laravel_router()
    {
        $this->registerLaravelRoutes();

        $routeRules[0]['match']['prefixes'] = ['*'];
        $routeRules[0]['match']['domains'] = ['*'];
        $this->assertCount(12, $this->matchRoutes($routeRules));

        $routeRules[0]['match']['domains'] = ['domain1.*', 'domain2.*'];
        $this->assertCount(12, $this->matchRoutes($routeRules));

        $routeRules[0]['match']['domains'] = ['domain1.*'];
        $routes = $this->matchRoutes($routeRules);
        $this->assertCount(6, $routes);
        foreach ($routes as $route) {
            $this->assertStringContainsString('domain1', $route['route']->getDomain());
        }

        $routeRules[0]['match']['domains'] = ['domain2.*'];
        $routes = $this->matchRoutes($routeRules);
        $this->assertCount(6, $routes);
        foreach ($routes as $route) {
            $this->assertStringContainsString('domain2', $route['route']->getDomain());
        }
    }

    /** @test */
    public function respects_prefixes_rule_for_laravel_router()
    {
        $this->registerLaravelRoutes();
        $routeRules[0]['match']['domains'] = ['*'];

        $routeRules[0]['match']['prefixes'] = ['*'];
        $this->assertCount(12, $this->matchRoutes($routeRules));

        $routeRules[0]['match']['prefixes'] = ['prefix1/*', 'prefix2/*'];
        $this->assertCount(8, $this->matchRoutes($routeRules));

        $routeRules[0]['match']['prefixes'] = ['prefix1/*'];
        $routes = $this->matchRoutes($routeRules);
        $this->assertCount(4, $routes);
        foreach ($routes as $route) {
            $this->assertTrue(Str::is('prefix1/*', $route['route']->uri()));
        }

        $routeRules[0]['match']['prefixes'] = ['prefix2/*'];
        $routes = $this->matchRoutes($routeRules);
        $this->assertCount(4, $routes);
        foreach ($routes as $route) {
            $this->assertTrue(Str::is('prefix2/*', $route['route']->uri()));
        }
    }

    /** @test */
    public function includes_route_if_listed_explicitly_for_laravel_router()
    {
        $this->registerLaravelRoutes();
        $mustInclude = 'domain1-1';
        $routeRules[0]['include'] = [$mustInclude];

        $routeRules[0]['match']['domains'] = ['domain1.*'];
        $routeRules[0]['match']['prefixes'] = ['prefix1/*'];
        $routes = $this->matchRoutes($routeRules);
        $oddRuleOut = collect($routes)->filter(fn($route) => $route['route']->getName() === $mustInclude);
        $this->assertCount(1, $oddRuleOut);
    }

    /** @test */
    public function includes_route_if_match_for_an_include_pattern_for_laravel_router()
    {
        $this->registerLaravelRoutes();
        $mustInclude = ['domain1-1', 'domain1-2'];
        $includePattern = 'domain1-*';
        $routeRules[0]['include'] = [$includePattern];

        $routeRules[0]['match']['domains'] = ['domain1.*'];
        $routeRules[0]['match']['prefixes'] = ['prefix1/*'];
        $routes = $this->matchRoutes($routeRules);
        $oddRuleOut = collect($routes)->filter(fn($route) => in_array($route['route']->getName(), $mustInclude));
        $this->assertCount(count($mustInclude), $oddRuleOut);
    }

    /** @test */
    public function exclude_route_if_listed_explicitly_for_laravel_router()
    {
        $this->registerLaravelRoutes();
        $mustNotInclude = 'prefix1.domain1-1';
        $routeRules[0]['exclude'] = [$mustNotInclude];

        $routeRules[0]['match']['domains'] = ['domain1.*'];
        $routeRules[0]['match']['prefixes'] = ['prefix1/*'];
        $routes = $this->matchRoutes($routeRules);
        $oddRuleOut = collect($routes)->filter(fn($route) => $route['route']->getName() === $mustNotInclude);
        $this->assertCount(0, $oddRuleOut);
    }

    /** @test */
    public function exclude_route_if_match_for_an_exclude_pattern_for_laravel_router()
    {
        $this->registerLaravelRoutes();
        $mustNotInclude = ['prefix1.domain1-1', 'prefix1.domain1-2'];
        $excludePattern = 'prefix1.domain1-*';
        $routeRules[0]['exclude'] = [$excludePattern];

        $routeRules[0]['match']['domains'] = ['domain1.*'];
        $routeRules[0]['match']['prefixes'] = ['prefix1/*'];
        $routes = $this->matchRoutes($routeRules);
        $oddRuleOut = collect($routes)->filter(fn($route) => in_array($route['route']->getName(), $mustNotInclude));
        $this->assertCount(0, $oddRuleOut);
    }

    /** @test */
    public function merges_routes_from_different_rule_groups_for_laravel_router()
    {
        $this->registerLaravelRoutes();

        $routeRules = [
            [
                'match' => [
                    'domains' => ['domain1.*'],
                    'prefixes' => ['prefix1/*'],
                ],
            ],
            [
                'match' => [
                    'domains' => ['domain2.*'],
                    'prefixes' => ['prefix2*'],
                ],
            ],
        ];

        $this->assertCount(4, $this->matchRoutes($routeRules));

        $routes = collect($this->matchRoutes($routeRules));
        $firstRuleGroup = $routes->filter(function ($route) {
            return Str::is('prefix1/*', $route['route']->uri())
                && Str::is('domain1.*', $route['route']->getDomain());
        });
        $this->assertCount(2, $firstRuleGroup);

        $secondRuleGroup = $routes->filter(function ($route) {
            return Str::is('prefix2/*', $route['route']->uri())
                && Str::is('domain2.*', $route['route']->getDomain());
        });
        $this->assertCount(2, $secondRuleGroup);
    }

    private function registerLaravelRoutes()
    {
        RouteFacade::group(['domain' => 'domain1.app.test'], function () {
            RouteFacade::post('/domain1-1', function () {
                return 'hi';
            })->name('domain1-1');
            RouteFacade::get('domain1-2', function () {
                return 'hi';
            })->name('domain1-2');
            RouteFacade::get('/prefix1/domain1-1', function () {
                return 'hi';
            })->name('prefix1.domain1-1');
            RouteFacade::get('prefix1/domain1-2', function () {
                return 'hi';
            })->name('prefix1.domain1-2');
            RouteFacade::get('/prefix2/domain1-1', function () {
                return 'hi';
            })->name('prefix2.domain1-1');
            RouteFacade::get('prefix2/domain1-2', function () {
                return 'hi';
            })->name('prefix2.domain1-2');
        });
        RouteFacade::group(['domain' => 'domain2.app.test'], function () {
            RouteFacade::post('/domain2-1', function () {
                return 'hi';
            })->name('domain2-1');
            RouteFacade::get('domain2-2', function () {
                return 'hi';
            })->name('domain2-2');
            RouteFacade::get('/prefix1/domain2-1', function () {
                return 'hi';
            })->name('prefix1.domain2-1');
            RouteFacade::get('prefix1/domain2-2', function () {
                return 'hi';
            })->name('prefix1.domain2-2');
            RouteFacade::get('/prefix2/domain2-1', function () {
                return 'hi';
            })->name('prefix2.domain2-1');
            RouteFacade::get('prefix2/domain2-2', function () {
                return 'hi';
            })->name('prefix2.domain2-2');
        });
    }

    protected function matchRoutes(array $routeRules): array
    {
        $matcher = new RouteMatcher();
        return $matcher->getRoutes($routeRules);
    }
}
