<?php

namespace Knuckles\Scribe;

use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Commands\GenerateDocumentation;
use Knuckles\Scribe\Commands\UpdateDocumentation;
use Knuckles\Scribe\Matching\RouteMatcher;
use Knuckles\Scribe\Matching\RouteMatcherInterface;

class ScribeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views/', 'scribe');

        $this->publishes([
            __DIR__ . '/../resources/views' => $this->app->basePath('resources/views/vendor/scribe'),
        ], 'scribe-views');

        $this->publishes([
            __DIR__ . '/../config/scribe.php' => $this->app->configPath('scribe.php'),
        ], 'scribe-config');

        $this->mergeConfigFrom(__DIR__ . '/../config/scribe.php', 'scribe');

        $this->bootRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocumentation::class,
                UpdateDocumentation::class,
            ]);
        }

        // Bind the route matcher implementation
        $this->app->bind(RouteMatcherInterface::class, config('scribe.routeMatcher', RouteMatcher::class));
    }

    /**
     * Initializing routes in the application.
     */
    protected function bootRoutes()
    {
        if (
            config('scribe.type', 'static') === 'laravel' &&
            config('scribe.laravel.autoload', false)
        ) {
            $this->loadRoutesFrom(
                __DIR__ . '/../routes/laravel.php'
            );
        }
    }
}
