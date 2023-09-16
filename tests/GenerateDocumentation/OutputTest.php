<?php

namespace Knuckles\Scribe\Tests\GenerateDocumentation;

use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tests\Fixtures\TestGroupController;
use Knuckles\Scribe\Tests\Fixtures\TestPartialResourceController;
use Knuckles\Scribe\Tests\Fixtures\TestPost;
use Knuckles\Scribe\Tests\Fixtures\TestPostBoundInterface;
use Knuckles\Scribe\Tests\Fixtures\TestPostController;
use Knuckles\Scribe\Tests\Fixtures\TestPostBoundInterfaceController;
use Knuckles\Scribe\Tests\Fixtures\TestPostUserController;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tests\TestHelpers;
use Knuckles\Scribe\Tools\Utils;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;
use Knuckles\Scribe\Extracting\Strategies;

class OutputTest extends BaseLaravelTest
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scribe.strategies' => [
            'metadata' => [
                Strategies\Metadata\GetFromDocBlocks::class,
                Strategies\Metadata\GetFromMetadataAttributes::class,
            ],
            'urlParameters' => [
                Strategies\UrlParameters\GetFromLaravelAPI::class,
                Strategies\UrlParameters\GetFromLumenAPI::class,
                Strategies\UrlParameters\GetFromUrlParamAttribute::class,
                Strategies\UrlParameters\GetFromUrlParamTag::class,
            ],
            'queryParameters' => [
                Strategies\QueryParameters\GetFromFormRequest::class,
                Strategies\QueryParameters\GetFromInlineValidator::class,
                Strategies\QueryParameters\GetFromQueryParamAttribute::class,
                Strategies\QueryParameters\GetFromQueryParamTag::class,
            ],
            'headers' => [
                Strategies\Headers\GetFromRouteRules::class,
                Strategies\Headers\GetFromHeaderAttribute::class,
                Strategies\Headers\GetFromHeaderTag::class,
            ],
            'bodyParameters' => [
                Strategies\BodyParameters\GetFromFormRequest::class,
                Strategies\BodyParameters\GetFromInlineValidator::class,
                Strategies\BodyParameters\GetFromBodyParamAttribute::class,
                Strategies\BodyParameters\GetFromBodyParamTag::class,
            ],
            'responses' => [
                Strategies\Responses\UseResponseAttributes::class,
                Strategies\Responses\UseTransformerTags::class,
                Strategies\Responses\UseApiResourceTags::class,
                Strategies\Responses\UseResponseTag::class,
                Strategies\Responses\UseResponseFileTag::class,
                Strategies\Responses\ResponseCalls::class,
            ],
            'responseFields' => [
                Strategies\ResponseFields\GetFromResponseFieldAttribute::class,
                Strategies\ResponseFields\GetFromResponseFieldTag::class,
            ],
        ],
        ]);
        config(['scribe.database_connections_to_transact' => []]);
        config(['scribe.routes.0.match.prefixes' => ['api/*']]);
        // Skip these ones for faster tests
        config(['scribe.openapi.enabled' => false]);
        config(['scribe.postman.enabled' => false]);
        // We want to have the same values for params each time
        config(['scribe.examples.faker_seed' => 1234]);

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

    protected function usingLaravelTypeDocs($app)
    {
        $app['config']->set('scribe.type', 'laravel');
        $app['config']->set('scribe.laravel.add_routes', true);
        $app['config']->set('scribe.laravel.docs_url', '/apidocs');
    }

    /**
     * @test
     * @define-env usingLaravelTypeDocs
     */
    public function generates_laravel_type_output()
    {
        RouteFacade::post('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        config(['scribe.postman.enabled' => true]);
        config(['scribe.openapi.enabled' => true]);

        $this->generateAndExpectConsoleOutput(
            "Wrote Blade docs to: vendor/orchestra/testbench-core/laravel/resources/views/scribe",
            "Wrote Laravel assets to: vendor/orchestra/testbench-core/laravel/public/vendor/scribe",
            "Wrote Postman collection to: vendor/orchestra/testbench-core/laravel/storage/app/scribe/collection.json",
            "Wrote OpenAPI specification to: vendor/orchestra/testbench-core/laravel/storage/app/scribe/openapi.yaml",
        );

        $this->assertFileExists($this->postmanOutputPath(true));
        $this->assertFileExists($this->openapiOutputPath(true));
        $this->assertFileExists($this->bladeOutputPath());

        $response = $this->get('/apidocs/');
        $response->assertStatus(200);
        $response = $this->get('/apidocs.postman');
        $response->assertStatus(200);
        $response = $this->get('/apidocs.openapi');
        $response->assertStatus(200);

        unlink($this->postmanOutputPath(true));
        unlink($this->openapiOutputPath(true));
        unlink($this->bladeOutputPath());
    }

    /** @test */
    public function supports_multi_docs_in_laravel_type_output()
    {
        RouteFacade::post('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        config(['scribe_admin' => config('scribe')]);
        $title = "The Real Admin API";
        config(['scribe_admin.title' => $title]);
        config(['scribe_admin.type' => 'laravel']);
        config(['scribe_admin.postman.enabled' => true]);
        config(['scribe_admin.openapi.enabled' => true]);

        $output = $this->generate(["--config" => "scribe_admin"]);
        $this->assertStringContainsString(
            "Wrote Blade docs to: vendor/orchestra/testbench-core/laravel/resources/views/scribe_admin", $output
        );
        $this->assertStringContainsString(
            "Wrote Laravel assets to: vendor/orchestra/testbench-core/laravel/public/vendor/scribe_admin", $output
        );
        $this->assertStringContainsString(
            "Wrote Postman collection to: vendor/orchestra/testbench-core/laravel/storage/app/scribe_admin/collection.json", $output
        );
        $this->assertStringContainsString(
            "Wrote OpenAPI specification to: vendor/orchestra/testbench-core/laravel/storage/app/scribe_admin/openapi.yaml", $output
        );

        $paths = collect([
            Storage::disk('local')->path('scribe_admin/collection.json'),
            Storage::disk('local')->path('scribe_admin/openapi.yaml'),
            View::getFinder()->find('scribe_admin/index'),
        ]);
        $paths->each(fn($path) => $this->assertFileContainsString($path, $title));
        $paths->each(fn($path) => unlink($path));

        $this->assertDirectoryExists(".scribe_admin");
        Utils::deleteDirectoryAndContents(".scribe_admin");
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
        config(['scribe.title' => 'GREAT API!']);
        config(['scribe.auth.enabled' => true]);
        config(['scribe.postman.overrides' => [
            'info.version' => '3.9.9',
        ]]);
        config([
            'scribe.routes.0.apply.headers' => [
                'Custom-Header' => 'NotSoCustom',
            ],
        ]);
        config(['scribe.postman.enabled' => true]);

        $this->generateAndExpectConsoleOutput(
            "Wrote HTML docs and assets to: public/docs/",
            "Wrote Postman collection to: public/docs/collection.json"
        );

        $generatedCollection = json_decode(file_get_contents($this->postmanOutputPath()), true);
        // The Postman ID varies from call to call; erase it to make the test data reproducible.
        $generatedCollection['info']['_postman_id'] = '';
        $fixtureCollection = json_decode(file_get_contents(__DIR__ . '/../Fixtures/collection.json'), true);

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

        config(['scribe.openapi.enabled' => true]);
        config(['scribe.openapi.overrides' => [
            'info.version' => '3.9.9',
        ]]);
        config([
            'scribe.routes.0.apply.headers' => [
                'Custom-Header' => 'NotSoCustom',
            ],
        ]);

        $this->generateAndExpectConsoleOutput(
            "Wrote HTML docs and assets to: public/docs/",
            "Wrote OpenAPI specification to: public/docs/openapi.yaml"
        );

        $generatedSpec = Yaml::parseFile($this->openapiOutputPath());
        $fixtureSpec = Yaml::parseFile(__DIR__ . '/../Fixtures/openapi.yaml');
        $this->assertEquals($fixtureSpec, $generatedSpec);
    }

    /** @test */
    public function can_append_custom_http_headers()
    {
        RouteFacade::get('/api/headers', [TestController::class, 'checkCustomHeaders']);
        config([
            'scribe.routes.0.apply.headers' => [
                'Authorization' => 'customAuthToken',
                'Custom-Header' => 'NotSoCustom',
            ],
        ]);
        $this->generate();

        $endpointDetails = Yaml::parseFile('.scribe/endpoints/00.yaml')['endpoints'][0];
        $this->assertEquals("customAuthToken", $endpointDetails['headers']["Authorization"]);
        $this->assertEquals("NotSoCustom", $endpointDetails['headers']["Custom-Header"]);
    }

    /** @test */
    public function can_parse_utf8_response()
    {
        RouteFacade::get('/api/utf8', [TestController::class, 'withUtf8ResponseTag']);

        $this->generate();

        $generatedHtml = file_get_contents($this->htmlOutputPath());
        $this->assertStringContainsString('Лорем ипсум долор сит амет', $generatedHtml);
    }

    /** @test */
    public function sorts_group_naturally_if_no_order_specified()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);
        RouteFacade::get('/api/action2', [TestGroupController::class, 'action2']);
        RouteFacade::get('/api/action10', [TestGroupController::class, 'action10']);

        $this->generate();

        $crawler = new Crawler(file_get_contents($this->htmlOutputPath()));
        $headings = $crawler->filter('h1')->getIterator();
        $this->assertCount(5, $headings); // intro, auth, three groups
        [$_, $_, $firstGroup, $secondGroup, $thirdGroup] = $headings;

        $this->assertEquals('1. Group 1', $firstGroup->textContent);
        $this->assertEquals('2. Group 2', $secondGroup->textContent);
        $this->assertEquals('10. Group 10', $thirdGroup->textContent);

    }

    /** @test */
    public function sorts_groups_and_endpoints_in_the_specified_order()
    {
        config(['scribe.groups.order' => [
            '10. Group 10',
            '1. Group 1' => [
                'GET /api/action1b',
                'GET /api/action1',
            ],
            '13. Group 13' => [
                'SG B' => [
                    'POST /api/action13d',
                    'GET /api/action13a',
                ],
                'SG A',
                'PUT /api/action13c',
            ],
        ]]);

        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);
        RouteFacade::get('/api/action2', [TestGroupController::class, 'action2']);
        RouteFacade::get('/api/action10', [TestGroupController::class, 'action10']);
        RouteFacade::get('/api/action13a', [TestGroupController::class, 'action13a']);
        RouteFacade::post('/api/action13b', [TestGroupController::class, 'action13b']);
        RouteFacade::put('/api/action13c', [TestGroupController::class, 'action13c']);
        RouteFacade::post('/api/action13d', [TestGroupController::class, 'action13d']);
        RouteFacade::get('/api/action13e', [TestGroupController::class, 'action13e']);

        $this->generate();

        $crawler = new Crawler(file_get_contents($this->htmlOutputPath()));
        $headings = $crawler->filter('h1')->getIterator();
        $this->assertCount(6, $headings); // intro, auth, four groups
        [$_, $_, $firstGroup, $secondGroup, $thirdGroup, $fourthGroup] = $headings;

        $this->assertEquals('10. Group 10', $firstGroup->textContent);
        $this->assertEquals('1. Group 1', $secondGroup->textContent);
        $this->assertEquals('13. Group 13', $thirdGroup->textContent);
        $this->assertEquals('2. Group 2', $fourthGroup->textContent);

        $firstGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($firstGroup->textContent).'"]');
        $this->assertEquals(1, $firstGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action10", $firstGroupEndpointsAndSubgroups->getNode(0)->textContent);

        $secondGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($secondGroup->textContent).'"]');
        $this->assertEquals(2, $secondGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action1b", $secondGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("GET api/action1", $secondGroupEndpointsAndSubgroups->getNode(1)->textContent);

        $thirdGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($thirdGroup->textContent).'"]');
        $this->assertEquals(8, $thirdGroupEndpointsAndSubgroups->count());
        $this->assertEquals("SG B", $thirdGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("POST api/action13d", $thirdGroupEndpointsAndSubgroups->getNode(1)->textContent);
        $this->assertEquals("GET api/action13a", $thirdGroupEndpointsAndSubgroups->getNode(2)->textContent);
        $this->assertEquals("SG A", $thirdGroupEndpointsAndSubgroups->getNode(3)->textContent);
        $this->assertEquals("GET api/action13e", $thirdGroupEndpointsAndSubgroups->getNode(4)->textContent);
        $this->assertEquals("PUT api/action13c", $thirdGroupEndpointsAndSubgroups->getNode(5)->textContent);
        $this->assertEquals("SG C", $thirdGroupEndpointsAndSubgroups->getNode(6)->textContent);
        $this->assertEquals("POST api/action13b", $thirdGroupEndpointsAndSubgroups->getNode(7)->textContent);
    }

    /** @test */
    public function sorts_groups_and_endpoints_in_the_specified_order_with_wildcard()
    {
        config(['scribe.groups.order' => [
            '10. Group 10',
            '*',
            '13. Group 13' => [
                'SG B' => [
                    'POST /api/action13d',
                    'GET /api/action13a',
                ],
                'SG A',
                'PUT /api/action13c',
            ],
        ]]);

        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);
        RouteFacade::get('/api/action2', [TestGroupController::class, 'action2']);
        RouteFacade::get('/api/action10', [TestGroupController::class, 'action10']);
        RouteFacade::get('/api/action13a', [TestGroupController::class, 'action13a']);
        RouteFacade::post('/api/action13b', [TestGroupController::class, 'action13b']);
        RouteFacade::put('/api/action13c', [TestGroupController::class, 'action13c']);
        RouteFacade::post('/api/action13d', [TestGroupController::class, 'action13d']);
        RouteFacade::get('/api/action13e', [TestGroupController::class, 'action13e']);

        $this->generate();

        $crawler = new Crawler(file_get_contents($this->htmlOutputPath()));
        $headings = $crawler->filter('h1')->getIterator();
        $this->assertCount(6, $headings); // intro, auth, four groups
        [$_, $_, $firstGroup, $secondGroup, $thirdGroup, $fourthGroup] = $headings;

        $this->assertEquals('10. Group 10', $firstGroup->textContent);
        $this->assertEquals('1. Group 1', $secondGroup->textContent);
        $this->assertEquals('2. Group 2', $thirdGroup->textContent);
        $this->assertEquals('13. Group 13', $fourthGroup->textContent);

        $firstGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($firstGroup->textContent).'"]');
        $this->assertEquals(1, $firstGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action10", $firstGroupEndpointsAndSubgroups->getNode(0)->textContent);

        $secondGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($secondGroup->textContent).'"]');
        $this->assertEquals(2, $secondGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action1", $secondGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("GET api/action1b", $secondGroupEndpointsAndSubgroups->getNode(1)->textContent);

        $fourthGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($fourthGroup->textContent).'"]');
        $this->assertEquals(8, $fourthGroupEndpointsAndSubgroups->count());
        $this->assertEquals("SG B", $fourthGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("POST api/action13d", $fourthGroupEndpointsAndSubgroups->getNode(1)->textContent);
        $this->assertEquals("GET api/action13a", $fourthGroupEndpointsAndSubgroups->getNode(2)->textContent);
        $this->assertEquals("SG A", $fourthGroupEndpointsAndSubgroups->getNode(3)->textContent);
        $this->assertEquals("GET api/action13e", $fourthGroupEndpointsAndSubgroups->getNode(4)->textContent);
        $this->assertEquals("PUT api/action13c", $fourthGroupEndpointsAndSubgroups->getNode(5)->textContent);
        $this->assertEquals("SG C", $fourthGroupEndpointsAndSubgroups->getNode(6)->textContent);
        $this->assertEquals("POST api/action13b", $fourthGroupEndpointsAndSubgroups->getNode(7)->textContent);
    }

    /** @test */
    public function merges_and_correctly_sorts_user_defined_endpoints()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action2', [TestGroupController::class, 'action2']);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);
        config(['scribe.groups.order' => [
            '1. Group 1',
            '5. Group 5',
            '4. Group 4',
            '2. Group 2',
        ]]);

        if (!is_dir('.scribe/endpoints')) mkdir('.scribe/endpoints', 0777, true);
        copy(__DIR__ . '/../Fixtures/custom.0.yaml', '.scribe/endpoints/custom.0.yaml');

        $this->generate();

        $crawler = new Crawler(file_get_contents($this->htmlOutputPath()));
        $headings = $crawler->filter('h1')->getIterator();
        $this->assertCount(6, $headings); // intro, auth, four groups
        [$_, $_, $firstGroup, $secondGroup, $thirdGroup, $fourthGroup] = $headings;

        $this->assertEquals('1. Group 1', $firstGroup->textContent);
        $this->assertEquals('5. Group 5', $secondGroup->textContent);
        $this->assertEquals('4. Group 4', $thirdGroup->textContent);
        $this->assertEquals('2. Group 2', $fourthGroup->textContent);

        $firstGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($firstGroup->textContent).'"]');
        $this->assertEquals(2, $firstGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action1", $firstGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("User defined", $firstGroupEndpointsAndSubgroups->getNode(1)->textContent);

        $secondGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($secondGroup->textContent).'"]');
        $this->assertEquals(2, $secondGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET group5", $secondGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("GET alsoGroup5", $secondGroupEndpointsAndSubgroups->getNode(1)->textContent);

        $thirdGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($thirdGroup->textContent).'"]');
        $this->assertEquals(1, $thirdGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET group4", $thirdGroupEndpointsAndSubgroups->getNode(0)->textContent);

        $fourthGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="'.Str::slug($fourthGroup->textContent).'"]');
        $this->assertEquals(1, $fourthGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action2", $fourthGroupEndpointsAndSubgroups->getNode(0)->textContent);
    }

    /** @test */
    public function will_not_overwrite_manually_modified_content_unless_force_flag_is_set()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);

        $this->generate();

        $authFilePath = '.scribe/auth.md';
        $firstGroupFilePath = '.scribe/endpoints/00.yaml';

        $group = Yaml::parseFile($firstGroupFilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals([], $group['endpoints'][0]['urlParameters']);
        $extraParam = [
            'name' => 'a_param',
            'description' => 'A URL param.',
            'required' => true,
            'example' => 6,
            'type' => 'integer',
            'enumValues' => [],
            'custom' => [],
        ];
        $group['endpoints'][0]['urlParameters']['a_param'] = $extraParam;
        file_put_contents($firstGroupFilePath, Yaml::dump(
            $group, 20, 2,
            Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP
        ));
        file_put_contents($authFilePath, 'Some other useful stuff.', FILE_APPEND);

        $this->generate();

        $group = Yaml::parseFile($firstGroupFilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals(['a_param' => $extraParam], $group['endpoints'][0]['urlParameters']);
        $this->assertStringContainsString('Some other useful stuff.', file_get_contents($authFilePath));

        $this->generate(['--force' => true]);

        $group = Yaml::parseFile($firstGroupFilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals([], $group['endpoints'][0]['urlParameters']);
        $this->assertStringNotContainsString('Some other useful stuff.', file_get_contents($authFilePath));
    }

    /** @test */
    public function generates_correct_url_params_from_resource_routes_and_field_bindings()
    {
        RouteFacade::prefix('providers/{provider:slug}')->group(function () {
            RouteFacade::resource('users.addresses', TestPartialResourceController::class)->parameters([
                'addresses' => 'address:uuid',
            ]);
        });
        config(['scribe.routes.0.match.prefixes' => ['*']]);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);

        $this->generate();

        $groupA = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('providers/{provider_slug}/users/{user_id}/addresses', $groupA['endpoints'][0]['uri']);
        $groupB = Yaml::parseFile('.scribe/endpoints/01.yaml');
        $this->assertEquals('providers/{provider_slug}/users/{user_id}/addresses/{uuid}', $groupB['endpoints'][0]['uri']);
    }

    /** @test */
    public function generates_correct_url_params_from_resource_routes_and_model_binding()
    {
        RouteFacade::resource('posts', TestPostController::class)->only('update');
        RouteFacade::resource('posts.users', TestPostUserController::class)->only('update');

        config(['scribe.routes.0.match.prefixes' => ['*']]);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);

        $this->generate();

        $group = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('posts/{slug}', $group['endpoints'][0]['uri']);
        $this->assertEquals('posts/{post_slug}/users/{id}', $group['endpoints'][1]['uri']);
    }

    /** @test */
    public function generates_correct_url_params_from_resource_routes_and_model_binding_with_bound_interfaces()
    {
        $this->app->bind(TestPostBoundInterface::class, fn() => new TestPost());

        RouteFacade::resource('posts', TestPostBoundInterfaceController::class)->only('update');

        config(['scribe.routes.0.match.prefixes' => ['*']]);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);

        $this->generate();

        $group = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('posts/{slug}', $group['endpoints'][0]['uri']);
    }

    /** @test */
    public function generates_correct_url_params_from_non_resource_routes_and_model_binding()
    {
        RouteFacade::get('posts/{post}/users', function (TestPost $post) {
        });

        config(['scribe.routes.0.match.prefixes' => ['*']]);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);

        $this->generate();

        $group = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('posts/{post_slug}/users', $group['endpoints'][0]['uri']);
    }

    /** @test */
    public function generates_from_camel_dir_if_noExtraction_flag_is_set()
    {
        config(['scribe.routes.0.exclude' => ['*']]);
        Utils::copyDirectory(__DIR__ . '/../Fixtures/.scribe', '.scribe');

        $output = $this->generate(['--no-extraction' => true]);

        $this->assertStringNotContainsString("Processing route", $output);

        $crawler = new Crawler(file_get_contents($this->htmlOutputPath()));
        [$intro, $auth] = $crawler->filter('h1 + p')->getIterator();
        $this->assertEquals('Heyaa introduction!👋', trim($intro->firstChild->textContent));
        $this->assertEquals('This is just a test.', trim($auth->firstChild->textContent));
        $group = $crawler->filter('h1')->getNode(2);
        $this->assertEquals('General', trim($group->textContent));
        $expectedEndpoint = $crawler->filter('h2');
        $this->assertCount(1, $expectedEndpoint);
        $this->assertEquals("Healthcheck", $expectedEndpoint->text());
    }

    /** @test */
    public function will_auto_set_content_type_to_multipart_if_file_params_are_present()
    {
        /**
         * @bodyParam param string required
         */
        RouteFacade::post('no-file', fn() => null);
        /**
         * @bodyParam a_file file required
         */
        RouteFacade::post('top-level-file', fn() => null);
        /**
         * @bodyParam data object
         * @bodyParam data.thing string
         * @bodyParam data.a_file file
         */
        RouteFacade::post('nested-file', fn() => null);
        config(['scribe.routes.0.match.prefixes' => ['*']]);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);

        $this->generate();

        $group = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('no-file', $group['endpoints'][0]['uri']);
        $this->assertEquals('application/json', $group['endpoints'][0]['headers']['Content-Type']);
        $this->assertEquals('top-level-file', $group['endpoints'][1]['uri']);
        $this->assertEquals('multipart/form-data', $group['endpoints'][1]['headers']['Content-Type']);
        $this->assertEquals('nested-file', $group['endpoints'][2]['uri']);
        $this->assertEquals('multipart/form-data', $group['endpoints'][2]['headers']['Content-Type']);

    }

    protected function postmanOutputPath(bool $laravelType = false): string
    {
        return $laravelType
            ? Storage::disk('local')->path('scribe/collection.json') : 'public/docs/collection.json';
    }

    protected function openapiOutputPath(bool $laravelType = false): string
    {
        return $laravelType
            ? Storage::disk('local')->path('scribe/openapi.yaml') : 'public/docs/openapi.yaml';
    }

    protected function htmlOutputPath(): string
    {
        return 'public/docs/index.html';
    }

    protected function bladeOutputPath(): string
    {
        return View::getFinder()->find('scribe/index');
    }
}
