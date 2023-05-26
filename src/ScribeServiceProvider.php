<?php

namespace Knuckles\Scribe;

use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Commands\DiffConfig;
use Knuckles\Scribe\Commands\GenerateDocumentation;
use Knuckles\Scribe\Commands\MakeStrategy;
use Knuckles\Scribe\Commands\Upgrade;
use Knuckles\Scribe\Matching\RouteMatcher;
use Knuckles\Scribe\Matching\RouteMatcherInterface;
use Knuckles\Scribe\Tools\BladeMarkdownEngine;
use Knuckles\Scribe\Tools\Utils;

class ScribeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerViews();

        $this->registerConfig();

        $this->bootRoutes();

        $this->registerCommands();

        $this->loadTranslations();
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/scribe'),
        ], 'scribe-translations');

        // Bind the route matcher implementation
        $this->app->bind(RouteMatcherInterface::class, config('scribe.routeMatcher', RouteMatcher::class));

        if (!class_exists('Str')) {
            // Lumen may not have the aliases set up, and we don't want to have to use the FQN in our blade files.
            class_alias(\Illuminate\Support\Str::class, 'Str');
        }
    }

    /**
     * Add docs routes for users that want their docs to pass through their Laravel app.
     */
    protected function bootRoutes()
    {
        if (
            config('scribe.type', 'static') === 'laravel' &&
            config('scribe.laravel.add_routes', false)
        ) {
            $routesPath = Utils::isLumen() ? __DIR__ . '/../routes/lumen.php' : __DIR__ . '/../routes/laravel.php';
            $this->loadRoutesFrom($routesPath);
        }
    }

    /**
     * Try to laod translations from path lists.
     *
     * @return void
     */
    protected function loadTranslations(): void
    {
        $paths = [
            $this->app->langPath('vendor/scribe'),
            __DIR__.'/../lang'
        ];

        foreach($paths as $path)
        {
            if(file_exists($path)){
                $this->loadJsonTranslationsFrom($path);
                return;
            }
        }
    }

    protected function registerViews(): void
    {
        // Register custom Markdown Blade compiler so we can automatically have MD views converted to HTML
        $this->app->view->getEngineResolver()
            ->register('blademd', fn() => new BladeMarkdownEngine($this->app['blade.compiler']));
        $this->app->view->addExtension('md.blade.php', 'blademd');

        $this->loadViewsFrom(__DIR__ . '/../resources/views/', 'scribe');

        // Publish views in separate, smaller groups for ease of end-user modifications
        $viewGroups = [
            'views' => '',
            'examples' => 'partials/example-requests',
            'themes' => 'themes',
            'markdown' => 'markdown',
        ];
        foreach ($viewGroups as $group => $path) {
            $this->publishes([
                __DIR__ . "/../resources/views/$path" => $this->app->basePath("resources/views/vendor/scribe/$path"),
            ], "scribe-$group");
        }
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/scribe.php' => $this->app->configPath('scribe.php'),
        ], 'scribe-config');

        $this->mergeConfigFrom(__DIR__ . '/../config/scribe.php', 'scribe');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocumentation::class,
                MakeStrategy::class,
                Upgrade::class,
                DiffConfig::class,
            ]);
        }
    }
}
