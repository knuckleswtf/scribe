<?php

namespace Knuckles\Scribe\Tests;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Knuckles\Scribe\ScribeServiceProvider;
use Orchestra\Testbench\TestCase;

class BaseLaravelTest extends TestCase
{
    use TestHelpers;
    use ArraySubsetAsserts;

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'test');
        $app['config']->set('database.connections.test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        ScribeServiceProvider::$customTranslationLayerLoaded = false;
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        $providers = [
            ScribeServiceProvider::class,
        ];
        if (class_exists(\Dingo\Api\Provider\LaravelServiceProvider::class)) {
            $providers[] = \Dingo\Api\Provider\LaravelServiceProvider::class;
        }
        return $providers;
    }

    protected function setConfig($configValues): void
    {
        foreach ($configValues as $key => $value) {
            config(["scribe.$key" => $value]);
            config(["scribe_new.$key" => $value]);
        }
    }
}
