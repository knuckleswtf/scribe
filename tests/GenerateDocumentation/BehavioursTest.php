<?php

namespace Knuckles\Scribe\Tests\GenerateDocumentation;

use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Route as RouteFacade;
use Knuckles\Scribe\Commands\GenerateDocumentation;
use Knuckles\Scribe\Scribe;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tests\Fixtures\TestGroupController;
use Knuckles\Scribe\Tests\Fixtures\TestIgnoreThisController;
use Knuckles\Scribe\Tests\Fixtures\TestPartialResourceController;
use Knuckles\Scribe\Tests\Fixtures\TestResourceController;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tests\TestHelpers;
use Knuckles\Scribe\Tools\Utils;

class BehavioursTest extends BaseLaravelTest
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig([
            'database_connections_to_transact' => [],
            'routes.0.match.prefixes' => ['api/*'],
            // Skip these for faster tests
            'openapi.enabled' => false,
            'postman.enabled' => false,
        ]);

        $factory = app(\Illuminate\Database\Eloquent\Factory::class);
        $factory->define(TestUser::class, function () {
            return [
                'id' => 4,
                'first_name' => 'Tested',
                'last_name' => 'Again',
                'email' => 'a@b.com',
            ];
        });
    }

    public function tearDown(): void
    {
        Utils::deleteDirectoryAndContents('public/docs');
        Utils::deleteDirectoryAndContents('.scribe');
    }

    /** @test */
    public function can_process_traditional_laravel_route_syntax_and_callable_tuple_syntax()
    {
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);
        RouteFacade::get('/api/array/test', [TestController::class, 'withEndpointDescription']);

        $this->generateAndExpectConsoleOutput(
            'Processed route: [GET] api/test',
            'Processed route: [GET] api/array/test'
        );
    }

    /** @test */
    public function processes_head_routes_as_head_not_get()
    {
        RouteFacade::addRoute('HEAD', '/api/test', [TestController::class, 'withEndpointDescription']);
        $this->generateAndExpectConsoleOutput('Processed route: [HEAD] api/test');
    }

    /**
     * @test
     * @see https://github.com/knuckleswtf/scribe/issues/53
     */
    public function can_process_closure_routes()
    {
        RouteFacade::get('/api/closure', function () {
            return 'hi';
        });
        $this->generateAndExpectConsoleOutput('Processed route: [GET] api/closure');
    }

    /**
     * @group dingo
     * @test
     */
    public function can_process_routes_on_dingo()
    {
        $api = app(\Dingo\Api\Routing\Router::class);
        $api->version('v1', function ($api) {
            $api->get('/closure', function () {
                return 'foo';
            });
            $api->get('/test', [TestController::class, 'withEndpointDescription']);
        });

        $this->setConfig(['routes.0.match.prefixes' => ['*']]);
        $this->setConfig(['routes.0.match.versions' => ['v1']]);

        $this->generateAndExpectConsoleOutput(
            'Processed route: [GET] closure',
            'Processed route: [GET] test'
        );
    }

    /** @test */
    public function calls_afterGenerating_hook()
    {
        $paths = [];
        Scribe::afterGenerating(function (array $outputPaths) use (&$paths) {
            $paths = $outputPaths;
        });
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        $this->generate();

        $this->assertEquals([
            'html' => realpath('public/docs/index.html'),
            'blade' => null,
            'postman' => realpath('public/docs/collection.json') ?: null,
            'openapi' => realpath('public/docs/openapi.yaml') ?: null,
            'assets' => [
                'js' => realpath('public/docs/js'),
                'css' => realpath('public/docs/css'),
                'images' => realpath('public/docs/images'),
            ],
        ], $paths);

        Scribe::afterGenerating(fn() => null);
    }

    /** @test */
    public function calls_bootstrap_hook()
    {
        $commandInstance = null;

        Scribe::bootstrap(function (GenerateDocumentation $command) use (&$commandInstance){
            $commandInstance = $command;
        });

        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        $this->generate();

        $this->assertTrue($commandInstance instanceof GenerateDocumentation);

        Scribe::bootstrap(fn() => null);
    }

    /** @test */
    public function skips_methods_and_classes_with_hidefromapidocumentation_tag()
    {
        RouteFacade::get('/api/skip', [TestController::class, 'skip']);
        RouteFacade::get('/api/skipClass', TestIgnoreThisController::class . '@dummy');
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        $this->generateAndExpectConsoleOutput(
            'Skipping route: [GET] api/skip',
            'Skipping route: [GET] api/skipClass',
            'Processed route: [GET] api/test'
        );
    }

    /** @test */
    public function warns_of_nonexistent_response_files()
    {
        RouteFacade::get('/api/non-existent', [TestController::class, 'withNonExistentResponseFile']);
        $this->generateAndExpectConsoleOutput('@responseFile i-do-not-exist.json does not exist');
    }

    /** @test */
    public function can_parse_resource_routes()
    {
        RouteFacade::resource('/api/users', TestResourceController::class)
            ->only(['index', 'store']);

        $output = $this->generate();

        $this->assertStringContainsString('Processed route: [GET] api/users', $output);
        $this->assertStringContainsString('Processed route: [POST] api/users', $output);

        $this->assertStringNotContainsString('Processed route: [PUT,PATCH] api/users/{user}', $output);
        $this->assertStringNotContainsString('Processed route: [DELETE] api/users/{user}', $output);
    }

    /** @test */
    public function supports_partial_resource_controller()
    {
        RouteFacade::resource('/api/users', TestPartialResourceController::class);

        $this->generateAndExpectConsoleOutput(
            'Processed route: [GET] api/users',
            'Processed route: [PUT,PATCH] api/users/{user}'
        );
    }

    /** @test */
    public function can_customise_static_output_path()
    {
        RouteFacade::get('/api/action1', TestGroupController::class . '@action1');

        $this->setConfig(['static.output_path' => 'static/docs']);
        $this->assertFileDoesNotExist('static/docs/index.html');

        $this->generate();

        $this->assertFileExists('static/docs/index.html');

        Utils::deleteDirectoryAndContents('static/');
    }

    /** @test */
    public function checks_for_upgrades_after_run_unless_disabled()
    {
        file_put_contents("config/scribe_test.php", str_replace("'logo' => false,", "", file_get_contents("config/scribe.php")));
        config(["scribe_test" => require "config/scribe_test.php"]);

        $output = $this->artisan('scribe:generate', ['--config' => 'scribe_test']);

        if (! FileFacade::exists(config_path("scribe.php"))) {
            $this->assertStringContainsString("No config file to upgrade.", $output);
        } else {
            $this->assertStringContainsString("Checking for any pending upgrades to your config file...", $output);
            $this->assertStringContainsString("`logo` will be added", $output);
        }

        $output = $this->artisan('scribe:generate', ['--config' => 'scribe_test', '--no-upgrade-check' => true]);
        $this->assertStringNotContainsString("Checking for any pending upgrades to your config file...", $output);

        unlink("config/scribe_test.php");
        Utils::deleteDirectoryAndContents(".scribe_test");
    }

    /** @test */
    public function can_generate_with_apiresource_tag_but_without_apiresourcemodel_tag()
    {
        RouteFacade::get('/api/test', [TestController::class, 'withEmptyApiResource']);
        $this->generateAndExpectConsoleOutput(
            "Couldn't detect an Eloquent API resource model",
            'Processed route: [GET] api/test'
        );
    }
}
