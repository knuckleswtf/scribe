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

    public function tearDown(): void
    {
        Utils::deleteDirectoryAndContents('public/docs');
        Utils::deleteDirectoryAndContents('.scribe');
    }

    /** @test */
    public function canProcessTraditionalLaravelRouteSyntaxAndCallableTupleSyntax()
    {
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);
        RouteFacade::get('/api/array/test', [TestController::class, 'withEndpointDescription']);

        $this->generateAndExpectConsoleOutput(expected: [
            'Processed route: [GET] api/test',
            'Processed route: [GET] api/array/test',
        ]);
    }

    /** @test */
    public function processesHeadRoutesAsHeadNotGet()
    {
        RouteFacade::addRoute('HEAD', '/api/test', [TestController::class, 'withEndpointDescription']);
        $this->generateAndExpectConsoleOutput(expected: ['Processed route: [HEAD] api/test']);
    }

    /**
     * @test
     *
     * @see https://github.com/knuckleswtf/scribe/issues/53
     */
    public function canProcessClosureRoutes()
    {
        RouteFacade::get('/api/closure', fn() => 'hi');
        $this->generateAndExpectConsoleOutput(expected: ['Processed route: [GET] api/closure']);
    }

    /** @test */
    public function callsAfterGeneratingHookWithCorrectPaths()
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

        Scribe::afterGenerating(fn() => null);
    }

    /** @test */
    public function callsBootstrapHook()
    {
        $commandInstance = null;

        Scribe::bootstrap(function (GenerateDocumentation $command) use (&$commandInstance) {
            $commandInstance = $command;
        });

        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        $this->generate();

        $this->assertTrue($commandInstance instanceof GenerateDocumentation);

        Scribe::bootstrap(fn() => null);
    }

    /** @test */
    public function skipsMethodsAndClassesWithHidefromapidocumentationTag()
    {
        RouteFacade::get('/api/skip', [TestController::class, 'skip']);
        RouteFacade::get('/api/skipClass', TestIgnoreThisController::class . '@dummy');
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        $this->generateAndExpectConsoleOutput(expected: [
            'Skipping route: [GET] api/skip',
            'Skipping route: [GET] api/skipClass',
            'Processed route: [GET] api/test',
        ]);
    }

    /** @test */
    public function warnsOfNonexistentResponseFiles()
    {
        RouteFacade::get('/api/non-existent', [TestController::class, 'withNonExistentResponseFile']);
        $this->generateAndExpectConsoleOutput(expected: ['@responseFile i-do-not-exist.json does not exist']);
    }

    /** @test */
    public function canParseResourceRoutes()
    {
        RouteFacade::resource('/api/users', TestResourceController::class)->only(['index', 'store']);

        $this->generateAndExpectConsoleOutput(
            expected: [
                'Processed route: [GET] api/users',
                'Processed route: [POST] api/users',
            ],
            notExpected: [
                'Processed route: [PUT,PATCH] api/users/{user}',
                'Processed route: [DELETE] api/users/{user}', ]
        );
    }

    /** @test */
    public function supportsPartialResourceController()
    {
        RouteFacade::resource('/api/users', TestPartialResourceController::class);

        $this->generateAndExpectConsoleOutput(expected: [
            'Processed route: [GET] api/users',
            'Processed route: [PUT,PATCH] api/users/{user}',
        ]);
    }

    /** @test */
    public function canCustomiseStaticOutputPath()
    {
        RouteFacade::get('/api/action1', TestGroupController::class . '@action1');

        $this->setConfig(['type' => 'static', 'static.output_path' => 'static/docs']);
        $this->assertFileDoesNotExist('static/docs/index.html');

        $this->generate();

        $this->assertFileExists('static/docs/index.html');

        Utils::deleteDirectoryAndContents('static/');
    }

    /** @test */
    public function canGenerateWithApiresourceTagButWithoutApiresourcemodelTag()
    {
        RouteFacade::get('/api/test', [TestController::class, 'withEmptyApiResource']);
        $this->generateAndExpectConsoleOutput(expected: [
            "Couldn't detect an Eloquent API resource model",
            'Processed route: [GET] api/test',
        ]);
    }
}
