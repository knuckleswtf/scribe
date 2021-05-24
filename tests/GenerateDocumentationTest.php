<?php

namespace Knuckles\Scribe\Tests;

use Illuminate\Support\Facades\Route as RouteFacade;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tests\Fixtures\TestGroupController;
use Knuckles\Scribe\Tests\Fixtures\TestIgnoreThisController;
use Knuckles\Scribe\Tests\Fixtures\TestPartialResourceController;
use Knuckles\Scribe\Tests\Fixtures\TestResourceController;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tools\Utils;
use Symfony\Component\Yaml\Yaml;

class GenerateDocumentationTest extends BaseLaravelTest
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scribe.database_connections_to_transact' => []]);

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

    /** @test */
    public function can_process_traditional_laravel_route_syntax()
    {
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Processed route: [GET] api/test', $output);
    }

    /** @test */
    public function can_process_traditional_laravel_head_routes()
    {
        RouteFacade::addRoute('HEAD', '/api/test', [TestController::class, 'withEndpointDescription']);

        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Processed route: [HEAD] api/test', $output);
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

        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Processed route: [GET] api/closure', $output);
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

        config(['scribe.routes.0.match.prefixes' => ['*']]);
        config(['scribe.routes.0.match.versions' => ['v1']]);
        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Processed route: [GET] closure', $output);
        $this->assertStringContainsString('Processed route: [GET] test', $output);
    }

    /** @test */
    public function can_process_callable_tuple_syntax()
    {
        RouteFacade::get('/api/array/test', [TestController::class, 'withEndpointDescription']);

        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Processed route: [GET] api/array/test', $output);
    }

    /** @test */
    public function can_skip_methods_and_classes_with_hidefromapidocumentation_tag()
    {
        RouteFacade::get('/api/skip', [TestController::class, 'skip']);
        RouteFacade::get('/api/skipClass', TestIgnoreThisController::class . '@dummy');
        RouteFacade::get('/api/test', [TestController::class, 'withEndpointDescription']);

        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Skipping route: [GET] api/skip', $output);
        $this->assertStringContainsString('Skipping route: [GET] api/skipClass', $output);
        $this->assertStringContainsString('Processed route: [GET] api/test', $output);
    }

    /** @test */
    public function can_skip_nonexistent_response_files()
    {
        RouteFacade::get('/api/non-existent', [TestController::class, 'withNonExistentResponseFile']);

        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('@responseFile i-do-not-exist.json does not exist', $output);
    }

    /** @test */
    public function can_parse_resource_routes()
    {
        RouteFacade::resource('/api/users', TestResourceController::class)
            ->only(['index', 'store']);

        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        config([
            'scribe.routes.0.apply.headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Processed route: [GET] api/users', $output);
        $this->assertStringContainsString('Processed route: [POST] api/users', $output);

        $this->assertStringNotContainsString('Processed route: [PUT,PATCH] api/users/{user}', $output);
        $this->assertStringNotContainsString('Processed route: [DELETE] api/users/{user}', $output);

        RouteFacade::apiResource('/api/users', TestResourceController::class)
            ->only(['index', 'store']);
        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Processed route: [GET] api/users', $output);
        $this->assertStringContainsString('Processed route: [POST] api/users', $output);

        $this->assertStringNotContainsString('Processed route: [PUT,PATCH] api/users/{user}', $output);
        $this->assertStringNotContainsString('Processed route: [DELETE] api/users/{user}', $output);
    }

    /** @test */
    public function supports_partial_resource_controller()
    {
        RouteFacade::resource('/api/users', TestPartialResourceController::class);

        config(['scribe.routes.0.prefixes' => ['api/*']]);

        $output = $this->artisan('scribe:generate');

        $this->assertStringContainsString('Processed route: [GET] api/users', $output);
        $this->assertStringContainsString('Processed route: [PUT,PATCH] api/users/{user}', $output);

    }

    /** @test */
    public function generated_postman_collection_file_is_correct()
    {
        RouteFacade::post('/api/withBodyParametersAsArray', [TestController::class, 'withBodyParametersAsArray']);
        RouteFacade::post('/api/withFormDataParams', [TestController::class, 'withFormDataParams']);
        RouteFacade::post('/api/withBodyParameters', [TestController::class, 'withBodyParameters']);
        RouteFacade::get('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        RouteFacade::get('/api/withAuthTag', [TestController::class, 'withAuthenticatedTag']);
        RouteFacade::get('/api/echoesUrlParameters/{param}/{param2}/{param3?}/{param4?}', [TestController::class, 'echoesUrlParameters']);
        // We want to have the same values for params each time
        config(['scribe.faker_seed' => 1234]);
        config(['scribe.title' => 'GREAT API!']);
        config(['scribe.auth.enabled' => true]);
        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        config(['scribe.postman.overrides' => [
            'info.version' => '3.9.9',
        ]]);
        config([
            'scribe.routes.0.apply.headers' => [
                'Custom-Header' => 'NotSoCustom',
            ],
        ]);
        config(['scribe.postman.enabled' => true]);
        config(['scribe.openapi.enabled' => false]);

        $this->artisan('scribe:generate');

        $generatedCollection = json_decode(file_get_contents(__DIR__ . '/../public/docs/collection.json'), true);
        // The Postman ID varies from call to call; erase it to make the test data reproducible.
        $generatedCollection['info']['_postman_id'] = '';
        $fixtureCollection = json_decode(file_get_contents(__DIR__ . '/Fixtures/collection.json'), true);

        $this->assertEquals($fixtureCollection, $generatedCollection);
    }

    /** @test */
    public function generated_openapi_spec_file_is_correct()
    {
        RouteFacade::post('/api/withBodyParametersAsArray', [TestController::class, 'withBodyParametersAsArray']);
        RouteFacade::post('/api/withFormDataParams', [TestController::class, 'withFormDataParams']);
        RouteFacade::get('/api/withResponseTag', [TestController::class, 'withResponseTag']);
        RouteFacade::get('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        RouteFacade::get('/api/withAuthTag', [TestController::class, 'withAuthenticatedTag']);
        RouteFacade::get('/api/echoesUrlParameters/{param}/{param2}/{param3?}/{param4?}', [TestController::class, 'echoesUrlParameters']);

        // We want to have the same values for params each time
        config(['scribe.faker_seed' => 1234]);
        config(['scribe.postman.enabled' => false]);
        config(['scribe.openapi.enabled' => true]);
        config(['scribe.openapi.overrides' => [
            'info.version' => '3.9.9',
        ]]);
        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        config([
            'scribe.routes.0.apply.headers' => [
                'Custom-Header' => 'NotSoCustom',
            ],
        ]);

        $this->artisan('scribe:generate');

        $generatedCollection = Yaml::parseFile(__DIR__ . '/../public/docs/openapi.yaml');
        $fixtureCollection = Yaml::parseFile(__DIR__ . '/Fixtures/openapi.yaml');
        $this->assertEquals($fixtureCollection, $generatedCollection);
    }

    /** @test */
    public function can_append_custom_http_headers()
    {
        RouteFacade::get('/api/headers', [TestController::class, 'checkCustomHeaders']);

        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        config([
            'scribe.routes.0.apply.headers' => [
                'Authorization' => 'customAuthToken',
                'Custom-Header' => 'NotSoCustom',
            ],
        ]);
        $this->artisan('scribe:generate');

        $endpointDetails = Yaml::parseFile(__DIR__ . '/../.scribe/endpoints/0.yaml')['endpoints'][0];
        $this->assertEquals("customAuthToken", $endpointDetails['headers']["Authorization"]);
        $this->assertEquals("NotSoCustom", $endpointDetails['headers']["Custom-Header"]);
    }

    /** @test */
    public function can_parse_utf8_response()
    {
        RouteFacade::get('/api/utf8', [TestController::class, 'withUtf8ResponseTag']);

        config(['scribe.routes.0.prefixes' => ['api/*']]);
        $this->artisan('scribe:generate');

        $generatedHtml = file_get_contents('public/docs/index.html');
        $this->assertStringContainsString('Лорем ипсум долор сит амет', $generatedHtml);
    }

    /** @test */
    public function sorts_group_naturally()
    {
        RouteFacade::get('/api/action1', TestGroupController::class . '@action1');
        RouteFacade::get('/api/action1b', TestGroupController::class . '@action1b');
        RouteFacade::get('/api/action2', TestGroupController::class . '@action2');
        RouteFacade::get('/api/action10', TestGroupController::class . '@action10');

        config(['scribe.routes.0.prefixes' => ['api/*']]);
        $this->artisan('scribe:generate');

        $this->assertFileExists(__DIR__ . '/../.scribe/endpoints/0.yaml');
        $this->assertFileExists(__DIR__ . '/../.scribe/endpoints/1.yaml');
        $this->assertFileExists(__DIR__ . '/../.scribe/endpoints/2.yaml');
        $this->assertEquals('1. Group 1', Yaml::parseFile(__DIR__ . '/../.scribe/endpoints/0.yaml')['name']);
        $this->assertEquals('2. Group 2', Yaml::parseFile(__DIR__ . '/../.scribe/endpoints/1.yaml')['name']);
        $this->assertEquals('10. Group 10', Yaml::parseFile(__DIR__ . '/../.scribe/endpoints/2.yaml')['name']);
    }

    /** @test */
    public function can_customise_static_output_path()
    {
        RouteFacade::get('/api/action1', TestGroupController::class . '@action1');

        config(['scribe.routes.0.prefixes' => ['*']]);
        config(['scribe.static.output_path' => 'static/docs']);
        $this->artisan('scribe:generate');

        $this->assertFileExists('static/docs/index.html');
        Utils::deleteDirectoryAndContents('static/docs');
    }

    /** @test */
    public function will_not_overwrite_manually_modified_content_unless_force_flag_is_set()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);
        config(['scribe.routes.0.prefixes' => ['api/*']]);

        $this->artisan('scribe:generate');

        $authFilePath = '.scribe/authentication.md';
        $group1FilePath = '.scribe/endpoints/0.yaml';

        $group = Yaml::parseFile($group1FilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals([], $group['endpoints'][0]['urlParameters']);
        $extraParam = [
            'name' => 'a_param',
            'description' => 'A URL param.',
            'required' => true,
            'example' => 6,
            'type' => 'integer',
        ];
        $group['endpoints'][0]['urlParameters']['a_param'] = $extraParam;
        file_put_contents($group1FilePath, Yaml::dump(
            $group, 10, 2,
            Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP
        ));
        sleep(1);
        file_put_contents($authFilePath, 'Some other useful stuff.', FILE_APPEND);

        $this->artisan('scribe:generate');

        $group = Yaml::parseFile($group1FilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals(['a_param' => $extraParam], $group['endpoints'][0]['urlParameters']);
        $this->assertStringContainsString('Some other useful stuff.', file_get_contents($authFilePath));

        $this->artisan('scribe:generate', ['--force' => true]);

        $group = Yaml::parseFile($group1FilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals([], $group['endpoints'][0]['urlParameters']);
        $this->assertStringNotContainsString('Some other useful stuff.', file_get_contents($authFilePath));
    }
}
