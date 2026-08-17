<?php

namespace Knuckles\Scribe\Tests;

use Illuminate\Foundation\Application;
use Knuckles\Scribe\Config;
use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Extracting\Shared\JsonApiResourceTools;
use Knuckles\Scribe\Extracting\Strategies;
use Knuckles\Scribe\ScribeServiceProvider;
use Orchestra\Testbench\TestCase;

use function Knuckles\Scribe\Config\configureStrategy;

/**
 * @internal
 *
 * @coversNothing
 */
class BaseLaravelTest extends TestCase
{
    use ArraySubsetAsserts;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig([
            'title' => config('app.name').' API Documentation',
            'type' => 'laravel',
            'theme' => 'default',
            'laravel.docs_url' => '/apidocs',
            'base_url' => config('app.base_url'),
            // Skip these for faster tests
            'postman.enabled' => false,
            'openApi.enabled' => false,
            'routes' => [
                [
                    'match' => [
                        'prefixes' => ['*'],
                        'domains' => ['*'],
                    ],
                ],
            ],
            'groups.default' => 'Endpoints',
            'auth' => [
                'enabled' => false,
                'default' => false,
                'in' => AuthIn::BEARER->value,
                'name' => 'key',
                'use_value' => env('SCRIBE_AUTH_KEY'),
                'placeholder' => '{YOUR_AUTH_KEY}',
            ],
            'strategies' => [
                'metadata' => Config\Defaults::METADATA_STRATEGIES,
                'urlParameters' => Config\Defaults::URL_PARAMETERS_STRATEGIES,
                'queryParameters' => Config\Defaults::QUERY_PARAMETERS_STRATEGIES,
                'headers' => Config\Defaults::HEADERS_STRATEGIES,
                'bodyParameters' => Config\Defaults::BODY_PARAMETERS_STRATEGIES,
                'responses' => configureStrategy(
                    Config\Defaults::RESPONSES_STRATEGIES,
                    Strategies\Responses\ResponseCalls::withSettings(
                        only: [],
                        except: ['*'], // Disabled to speed up tests
                        config: [
                            'app.env' => 'documentation',
                        ],
                    )
                ),
                'responseFields' => Config\Defaults::RESPONSE_FIELDS_STRATEGIES,
            ],
            'examples.faker_seed' => 1234,
            'database_connections_to_transact' => [],
        ]);
    }

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
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        // Testbench 10 ships this on, testbench 11 ships it off. When it is on, Laravel
        // registers a `GET storage/{path}` route, which Scribe then documents, throwing
        // off every route/group count in the tests. Pin it so the suite doesn't depend
        // on the testbench version.
        $app['config']->set('filesystems.disks.local.serve', false);

        ScribeServiceProvider::$customTranslationLayerLoaded = false;
    }

    /**
     * @param  Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ScribeServiceProvider::class,
        ];
    }

    protected function skipIfNoJsonApiResources(): void
    {
        if (! JsonApiResourceTools::isSupported()) {
            $this->markTestSkipped('JSON:API resources require Laravel 12.45 or later.');
        }
    }

    protected function setConfig($configValues): void
    {
        foreach ($configValues as $key => $value) {
            config(["scribe.{$key}" => $value]);
        }
    }
}
