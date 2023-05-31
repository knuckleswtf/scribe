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
use Knuckles\Scribe\Writing\CustomTranslationsLoader;

class ScribeServiceProvider extends ServiceProvider
{
    public static bool $customTranslationLayerLoaded = false;

    public function boot()
    {
        $this->registerViews();

        $this->registerConfig();

        $this->bootRoutes();

        $this->registerCommands();

        $this->configureTranslations();

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

    protected function configureTranslations(): void
    {
        $this->publishes([
            __DIR__.'/../lang/' => $this->app->langPath(),
        ], 'scribe-translations');

        $this->loadTranslationsFrom($this->app->langPath('scribe.php'), 'scribe');
        $this->loadTranslationsFrom(realpath(__DIR__ . '/../lang'), 'scribe');
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

    // Allows our custom translation layer to be loaded on demand,
    // so we minimize issues with interference from framework/package/environment.
    // ALso, Laravel's `app->runningInConsole()` isn't reliable enough. See issue #676
    public function loadCustomTranslationLayer(): void
    {
        $this->app->extend('translation.loader', function ($defaultFileLoader) {
            return new CustomTranslationsLoader($defaultFileLoader);
        });
        $this->app->forgetInstance('translator');
        self::$customTranslationLayerLoaded = true;
    }
}
