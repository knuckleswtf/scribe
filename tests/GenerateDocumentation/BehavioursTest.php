<?php

/** @noinspection NonAsciiCharacters */

namespace Knuckles\Scribe\Tests\GenerateDocumentation;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
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

/**
 * @internal
 *
 * @coversNothing
 */
class BehavioursTest extends BaseLaravelTest
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = app(Factory::class);
        $factory->define(TestUser::class, function () {
            return [
                'id' => 4,
                'first_name' => 'Tested',
                'last_name' => 'Again',
                'email' => 'a@b.com',
            ];
        });
    }

    protected function tearDown(): void
    {
        Utils::deleteDirectoryAndContents('public/docs');
        Utils::deleteDirectoryAndContents('.scribe');
    }

    /** @test */
    public function can_process_traditional_laravel_route_syntax_and_callable_tuple_syntax()
    {
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);
        RouteFacade::get('/api/array/test', [TestController::class, 'withEndpointDescription']);

        $this->generateAndExpectConsoleOutput(expected: [
            '[GET] api/test',
            '[GET] api/array/test',
        ]);
    }

    /** @test */
    public function processes_head_routes_as_head_not_get()
    {
        RouteFacade::addRoute('HEAD', '/api/test', [TestController::class, 'withEndpointDescription']);
        $this->generateAndExpectConsoleOutput(expected: ['[HEAD] api/test']);
    }

    /**
     * @test
     *
     * @see https://github.com/knuckleswtf/scribe/issues/53
     */
    public function can_process_closure_routes()
    {
        RouteFacade::get('/api/closure', fn () => 'hi');
        $this->generateAndExpectConsoleOutput(expected: ['[GET] api/closure']);
    }

    /** @test */
    public function calls_after_generating_hook_with_correct_paths()
    {
        $paths = [];
        Scribe::afterGenerating(function (array $outputPaths) use (&$paths) {
            $paths = $outputPaths;
        });
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        $this->setConfig([
            'type' => 'laravel',
            'laravel.add_routes' => true,
            'laravel.docs_url' => '/apidocs',
            'postman.enabled' => true,
            'openapi.enabled' => true,
        ]);
        $this->generate();

        $ノ = DIRECTORY_SEPARATOR; // Cross-platform
        $this->assertEquals([
            'html' => null,
            'blade' => resource_path("views{$ノ}scribe{$ノ}index.blade.php"),
            'postman' => Storage::disk('local')->path("scribe{$ノ}collection.json"),
            'openapi' => Storage::disk('local')->path("scribe{$ノ}openapi.yaml"),
            'assets' => [
                'js' => public_path("vendor{$ノ}scribe{$ノ}js"),
                'css' => public_path("vendor{$ノ}scribe{$ノ}css"),
                'images' => public_path("vendor{$ノ}scribe{$ノ}images"),
            ],
        ], $paths);

        $this->setConfig([
            'type' => 'static',
            'static.output_path' => 'public/docs',
            'postman.enabled' => false,
            'openapi.enabled' => false,
        ]);
        $this->generate();
        $this->assertEquals([
            'html' => realpath("public{$ノ}docs{$ノ}index.html"),
            'blade' => null,
            'postman' => null,
            'openapi' => null,
            'assets' => [
                'js' => realpath("public{$ノ}docs{$ノ}js"),
                'css' => realpath("public{$ノ}docs{$ノ}css"),
                'images' => realpath("public{$ノ}docs{$ノ}images"),
            ],
        ], $paths);

        Scribe::afterGenerating(fn () => null);
    }

    /** @test */
    public function calls_bootstrap_hook()
    {
        $commandInstance = null;

        Scribe::bootstrap(function (GenerateDocumentation $command) use (&$commandInstance) {
            $commandInstance = $command;
        });

        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        $this->generate();

        $this->assertTrue($commandInstance instanceof GenerateDocumentation);

        Scribe::bootstrap(fn () => null);
    }

    /** @test */
    public function skips_methods_and_classes_with_hidefromapidocumentation_tag()
    {
        RouteFacade::get('/api/skip', [TestController::class, 'skip']);
        RouteFacade::get('/api/skipClass', TestIgnoreThisController::class.'@dummy');
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        $this->generateAndExpectConsoleOutput(expected: [
            'Skipping route: [GET] api/skip',
            'Skipping route: [GET] api/skipClass',
            '[GET] api/test',
        ]);
    }

    /** @test */
    public function warns_of_nonexistent_response_files()
    {
        RouteFacade::get('/api/non-existent', [TestController::class, 'withNonExistentResponseFile']);
        $this->generateAndExpectConsoleOutput(expected: ['@responseFile i-do-not-exist.json does not exist']);
    }

    /** @test */
    public function can_parse_resource_routes()
    {
        RouteFacade::resource('/api/users', TestResourceController::class)->only(['index', 'store']);

        $this->generateAndExpectConsoleOutput(
            expected: [
                '[GET] api/users',
                '[POST] api/users',
            ],
            notExpected: [
                '[PUT|PATCH] api/users/{user}',
                '[DELETE] api/users/{user}', ]
        );
    }

    /** @test */
    public function supports_partial_resource_controller()
    {
        RouteFacade::resource('/api/users', TestPartialResourceController::class);

        $this->generateAndExpectConsoleOutput(expected: [
            '[GET] api/users',
            '[PUT|PATCH] api/users/{user}',
        ]);
    }

    /** @test */
    public function can_customise_static_output_path()
    {
        RouteFacade::get('/api/action1', TestGroupController::class.'@action1');

        $this->setConfig(['type' => 'static', 'static.output_path' => 'static/docs']);
        $this->assertFileDoesNotExist('static/docs/index.html');

        $this->generate();

        $this->assertFileExists('static/docs/index.html');

        Utils::deleteDirectoryAndContents('static/');
    }

    /** @test */
    public function can_generate_with_apiresource_tag_but_without_apiresourcemodel_tag()
    {
        RouteFacade::get('/api/test', [TestController::class, 'withEmptyApiResource']);
        $this->generateAndExpectConsoleOutput(expected: [
            "Couldn't detect an Eloquent API resource model",
            '[GET] api/test',
        ]);
    }
}
