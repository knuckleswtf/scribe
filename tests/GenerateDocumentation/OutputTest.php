<?php

namespace Knuckles\Scribe\Tests\GenerateDocumentation;

use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
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

class OutputTest extends BaseLaravelTest
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

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
        config(['scribe.type' => 'laravel']);
        config(['scribe.postman.enabled' => true]);
        config(['scribe.openapi.enabled' => true]);

        $this->generate();

        $this->assertFileExists($this->postmanOutputPath(true));
        $this->assertFileExists($this->openapiOutputPath(true));
        $this->assertFileExists($this->bladeOutputPath());

        $response = $this->get('/apidocs/');
        $response->assertStatus(200);
        $response = $this->get('/apidocs.postman');
        $response->assertStatus(200);
        $response = $this->get('/apidocs.openapi');
        $response->assertStatus(200);

        config(['scribe.laravel.add_routes' => false]);
        config(['scribe.laravel.docs_url' => '/apidocs']);

        unlink($this->postmanOutputPath(true));
        unlink($this->openapiOutputPath(true));
        unlink($this->bladeOutputPath());
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

        $this->generate();

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

        $this->generate();

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
    public function sorts_group_naturally()
    {
        RouteFacade::get('/api/action1', TestGroupController::class . '@action1');
        RouteFacade::get('/api/action1b', TestGroupController::class . '@action1b');
        RouteFacade::get('/api/action2', TestGroupController::class . '@action2');
        RouteFacade::get('/api/action10', TestGroupController::class . '@action10');

        $this->generate();

        $this->assertEquals('1. Group 1', Yaml::parseFile('.scribe/endpoints/00.yaml')['name']);
        $this->assertEquals('2. Group 2', Yaml::parseFile('.scribe/endpoints/01.yaml')['name']);
        $this->assertEquals('10. Group 10', Yaml::parseFile('.scribe/endpoints/02.yaml')['name']);
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
                'POST /api/action13b',
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

        $this->assertEquals('10. Group 10', Yaml::parseFile('.scribe/endpoints/00.yaml')['name']);
        $secondGroup = Yaml::parseFile('.scribe/endpoints/01.yaml');
        $this->assertEquals('1. Group 1', $secondGroup['name']);
        $thirdGroup = Yaml::parseFile('.scribe/endpoints/02.yaml');
        $this->assertEquals('13. Group 13', $thirdGroup['name']);
        $this->assertEquals('2. Group 2', Yaml::parseFile('.scribe/endpoints/03.yaml')['name']);

        $this->assertEquals('api/action1b', $secondGroup['endpoints'][0]['uri']);
        $this->assertEquals('GET', $secondGroup['endpoints'][0]['httpMethods'][0]);
        $this->assertEquals('api/action1', $secondGroup['endpoints'][1]['uri']);
        $this->assertEquals('GET', $secondGroup['endpoints'][1]['httpMethods'][0]);

        $this->assertEquals('api/action13d', $thirdGroup['endpoints'][0]['uri']);
        $this->assertEquals('POST', $thirdGroup['endpoints'][0]['httpMethods'][0]);
        $this->assertEquals('api/action13a', $thirdGroup['endpoints'][1]['uri']);
        $this->assertEquals('GET', $thirdGroup['endpoints'][1]['httpMethods'][0]);
        $this->assertEquals('api/action13e', $thirdGroup['endpoints'][2]['uri']);
        $this->assertEquals('GET', $thirdGroup['endpoints'][2]['httpMethods'][0]);
        $this->assertEquals('api/action13c', $thirdGroup['endpoints'][3]['uri']);
        $this->assertEquals('PUT', $thirdGroup['endpoints'][3]['httpMethods'][0]);
        $this->assertEquals('api/action13b', $thirdGroup['endpoints'][4]['uri']);
        $this->assertEquals('POST', $thirdGroup['endpoints'][4]['httpMethods'][0]);
    }

    /** @test */
    public function will_not_overwrite_manually_modified_content_unless_force_flag_is_set()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);

        $this->generate();

        $authFilePath = '.scribe/auth.md';
        $group1FilePath = '.scribe/endpoints/00.yaml';

        $group = Yaml::parseFile($group1FilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals([], $group['endpoints'][0]['urlParameters']);
        $extraParam = [
            'name' => 'a_param',
            'description' => 'A URL param.',
            'required' => true,
            'example' => 6,
            'type' => 'integer',
            'custom' => [],
        ];
        $group['endpoints'][0]['urlParameters']['a_param'] = $extraParam;
        file_put_contents($group1FilePath, Yaml::dump(
            $group, 20, 2,
            Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP
        ));
        file_put_contents($authFilePath, 'Some other useful stuff.', FILE_APPEND);

        $this->generate();

        $group = Yaml::parseFile($group1FilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals(['a_param' => $extraParam], $group['endpoints'][0]['urlParameters']);
        $this->assertStringContainsString('Some other useful stuff.', file_get_contents($authFilePath));

        $this->generate(['--force' => true]);

        $group = Yaml::parseFile($group1FilePath);
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
        RouteFacade::get('posts/{post}/users', function(TestPost $post) {});

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
        Utils::copyDirectory(__DIR__.'/../Fixtures/.scribe', '.scribe');

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
    public function merges_and_correctly_sorts_user_defined_endpoints()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action2', [TestGroupController::class, 'action2']);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);
        if (!is_dir('.scribe/endpoints'))
            mkdir('.scribe/endpoints', 0777, true);
        copy(__DIR__ . '/../Fixtures/custom.0.yaml', '.scribe/endpoints/custom.0.yaml');

        $this->generate();

        $crawler = new Crawler(file_get_contents($this->htmlOutputPath()));
        $headings = $crawler->filter('h1')->getIterator();
        // There should only be six headings — intro, auth and four groups
        $this->assertCount(6, $headings);
        [$_, $_, $group1, $group2, $group3, $group4] = $headings;
        $this->assertEquals('1. Group 1', trim($group1->textContent));
        $this->assertEquals('5. Group 5', trim($group2->textContent));
        $this->assertEquals('4. Group 4', trim($group3->textContent));
        $this->assertEquals('2. Group 2', trim($group4->textContent));
        $expectedEndpoints = $crawler->filter('h2');
        $this->assertEquals(6, $expectedEndpoints->count());
        // Enforce the order of the endpoints
        // Ideally, we should also check the groups they're under
        $this->assertEquals("Some endpoint.", $expectedEndpoints->getNode(0)->textContent);
        $this->assertEquals("User defined", $expectedEndpoints->getNode(1)->textContent);
        $this->assertEquals("GET withBeforeGroup", $expectedEndpoints->getNode(2)->textContent);
        $this->assertEquals("GET belongingToAnEarlierBeforeGroup", $expectedEndpoints->getNode(3)->textContent);
        $this->assertEquals("GET withAfterGroup", $expectedEndpoints->getNode(4)->textContent);
        $this->assertEquals("GET api/action2", $expectedEndpoints->getNode(5)->textContent);
    }

    /** @test */
    public function respects_endpoints_and_group_sort_order()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);
        RouteFacade::get('/api/action2', [TestGroupController::class, 'action2']);
        config(['scribe.routes.0.apply.response_calls.methods' => []]);

        $this->generate();

        // First: verify the current order of the groups and endpoints
        $crawler = new Crawler(file_get_contents($this->htmlOutputPath()));
        $h1s = $crawler->filter('h1');
        $this->assertEquals('1. Group 1', trim($h1s->getNode(2)->textContent));
        $this->assertEquals('2. Group 2', trim($h1s->getNode(3)->textContent));
        $expectedEndpoints = $crawler->filter('h2');
        $this->assertEquals("Some endpoint.", $expectedEndpoints->getNode(0)->textContent);
        $this->assertEquals("Another endpoint.", $expectedEndpoints->getNode(1)->textContent);
        $this->assertEquals("GET api/action2", $expectedEndpoints->getNode(2)->textContent);

        // Now swap the endpoints
        $group = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals('api/action1b', $group['endpoints'][1]['uri']);
        $action1 = $group['endpoints'][0];
        $group['endpoints'][0] = $group['endpoints'][1];
        $group['endpoints'][1] = $action1;
        file_put_contents('.scribe/endpoints/00.yaml', Yaml::dump(
            $group, 20, 2,
            Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP
        ));
        // And then the groups
        rename('.scribe/endpoints/00.yaml', '.scribe/endpoints/temp.yaml');
        rename('.scribe/endpoints/01.yaml', '.scribe/endpoints/00.yaml');
        rename('.scribe/endpoints/temp.yaml', '.scribe/endpoints/1.yaml');

        $this->generate();

        $crawler = new Crawler(file_get_contents($this->htmlOutputPath()));
        $h1s = $crawler->filter('h1');
        $this->assertEquals('2. Group 2', trim($h1s->getNode(2)->textContent));
        $this->assertEquals('1. Group 1', trim($h1s->getNode(3)->textContent));
        $expectedEndpoints = $crawler->filter('h2');
        $this->assertEquals("GET api/action2", $expectedEndpoints->getNode(0)->textContent);
        $this->assertEquals("Another endpoint.", $expectedEndpoints->getNode(1)->textContent);
        $this->assertEquals("Some endpoint.", $expectedEndpoints->getNode(2)->textContent);
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
