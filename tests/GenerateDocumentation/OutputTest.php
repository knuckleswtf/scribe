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
use function Knuckles\Scribe\Config\configureStrategy;

class OutputTest extends BaseLaravelTest
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

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
     * @test
     */
    public function generates_static_type_output()
    {
        RouteFacade::post('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        $this->setConfig(['type' => 'static']);
        $this->setConfig(['postman.enabled' => true]);
        $this->setConfig(['openapi.enabled' => true]);

        $this->generateAndExpectConsoleOutput(expected: [
            "Wrote HTML docs and assets to: public/docs/",
            "Wrote Postman collection to: public/docs/collection.json"
        ]);

        $this->assertFileExists($this->postmanOutputPath(true));
        $this->assertFileExists($this->openapiOutputPath(true));
        $this->assertFileExists($this->htmlOutputPath());

        unlink($this->postmanOutputPath(true));
        unlink($this->openapiOutputPath(true));
        unlink($this->bladeOutputPath());
    }

    /** @test */
    public function supports_multi_docs_in_laravel_type_output()
    {
        $this->generate_with_paths(configName: "scribe_admin");
    }

    /** @test */
    public function supports_custom_scribe_directory()
    {
        $this->generate_with_paths(configName: "scribe_admin", intermediateOutputDirectory: '5.5/Apple/26');
    }

    private function generate_with_paths($configName, $intermediateOutputDirectory = null)
    {
        RouteFacade::post('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        config([$configName => config('scribe')]);
        $title = "The Real Admin API";
        config(["{$configName}.title" => $title]);
        config(["{$configName}.type" => 'laravel']);
        config(["{$configName}.postman.enabled" => true]);
        config(["{$configName}.openapi.enabled" => true]);

        $pathOptions = ["--config" => $configName];
        if ($intermediateOutputDirectory) {
            $pathOptions["--scribe-dir"] = $intermediateOutputDirectory;
        }
        $this->generateAndExpectConsoleOutput($pathOptions, [
            "Wrote Blade docs to: vendor/orchestra/testbench-core/laravel/resources/views/{$configName}",
            "Wrote Laravel assets to: vendor/orchestra/testbench-core/laravel/public/vendor/{$configName}",
            "Wrote Postman collection to: vendor/orchestra/testbench-core/laravel/storage/app/{$configName}/collection.json",
            "Wrote OpenAPI specification to: vendor/orchestra/testbench-core/laravel/storage/app/{$configName}/openapi.yaml",
        ]);

        $paths = collect([
            Storage::disk('local')->path("{$configName}/collection.json"),
            Storage::disk('local')->path("{$configName}/openapi.yaml"),
            View::getFinder()->find("{$configName}/index"),
        ]);
        $paths->each(fn($path) => $this->assertFileContainsString($path, $title));
        $paths->each(fn($path) => unlink($path));

        $this->assertDirectoryExists($intermediateOutputDirectory ?: ".{$configName}");
        Utils::deleteDirectoryAndContents($intermediateOutputDirectory ?: ".{$configName}");
    }

    /** @test */
    public function generates_and_adds_routes()
    {
        RouteFacade::post('/api/withBodyParameters', [TestController::class, 'withBodyParameters']);

        $this->setConfig([
            'type' => 'laravel',
            'laravel.add_routes' => true,
            'postman.enabled' => true,
            'openapi.enabled' => true,
        ]);
        $this->generate();

        $response = $this->get('/docs');
        $response->assertStatus(200);
        $response = $this->get('/docs.postman');
        $response->assertStatus(200);
        $response = $this->get('/docs.openapi');
        $response->assertStatus(200);
    }

    /** @test */
    public function generated_postman_collection_file_is_correct()
    {
        if (phpversion() < 8.3) {
            // See https://github.com/FakerPHP/Faker/issues/694
            $this->markTestSkipped('Faker seeding changed in PHP 8.3');
        }

        RouteFacade::post('/api/withBodyParametersAsArray', [TestController::class, 'withBodyParametersAsArray']);
        RouteFacade::post('/api/withFormDataParams', [TestController::class, 'withFormDataParams']);
        RouteFacade::post('/api/withBodyParameters', [TestController::class, 'withBodyParameters']);
        RouteFacade::get('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        RouteFacade::get('/api/withAuthTag', [TestController::class, 'withAuthenticatedTag']);
        RouteFacade::get('/api/echoesUrlParameters/{param}/{param2}/{param3?}/{param4?}', [TestController::class, 'echoesUrlParameters']);
        $this->setConfig([
            'title' => 'GREAT API!',
            'auth.enabled' => true,
            'postman.enabled' => true,
            'postman.overrides' => [
                'info.version' => '3.9.9',
            ],
            'strategies.headers' => [
                ...config('scribe.strategies.headers'),
                Strategies\StaticData::withSettings(data: ['Custom-Header' => 'NotSoCustom']),
            ],
        ]);
        $this->enableResponseCalls();

        $this->generateAndExpectConsoleOutput(expected: [
            "Wrote Blade docs to: vendor/orchestra/testbench-core/laravel/resources/views/scribe",
            "Wrote Laravel assets to: vendor/orchestra/testbench-core/laravel/public/vendor/scribe",
            "Wrote Postman collection to: vendor/orchestra/testbench-core/laravel/storage/app/scribe/collection.json",
        ]);

        $generatedCollection = json_decode(file_get_contents($this->postmanOutputPath()), true);
        // The Postman ID varies from call to call; erase it to make the test data reproducible.
        $generatedCollection['info']['_postman_id'] = '';
        $fixtureCollection = json_decode(file_get_contents(__DIR__ . '/../Fixtures/collection.json'), true);

        // Laravel 11 began adding CORS headers by default
        foreach ($generatedCollection["item"] as &$group) {
            foreach ($group["item"] as &$endpoint) {
                foreach ($endpoint["response"] as &$response) {
                    $response["header"] = array_filter($response["header"], fn ($header) => $header["key"] !== "access-control-allow-origin");
                    $response['header'] = array_map(
                        fn (array $header) => strtolower($header['key']) === 'content-type'
                            ? [...$header, 'value' => strtolower($header['value'])]
                            : $header,
                        $response['header']
                    );
                }
            }
        }
        $this->assertEquals($fixtureCollection, $generatedCollection);
    }

    /** @test */
    public function generated_openapi_spec_file_is_correct()
    {
        if (phpversion() < 8.3) {
            // See https://github.com/FakerPHP/Faker/issues/694
            $this->markTestSkipped('Faker seeding changed in PHP 8.3');
        }

        RouteFacade::post('/api/withBodyParametersAsArray', [TestController::class, 'withBodyParametersAsArray']);
        RouteFacade::post('/api/withFormDataParams', [TestController::class, 'withFormDataParams']);
        RouteFacade::get('/api/withResponseTag', [TestController::class, 'withResponseTag']);
        RouteFacade::get('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        RouteFacade::get('/api/withAuthTag', [TestController::class, 'withAuthenticatedTag']);
        RouteFacade::get('/api/echoesUrlParameters/{param}/{param2}/{param3?}/{param4?}', [TestController::class, 'echoesUrlParameters']);

        $this->setConfig([
            'openapi.enabled' => true,
            'openapi.overrides' => [
                'info.version' => '3.9.9',
            ],
            'strategies.headers' =>  [
                ...config('scribe.strategies.headers'),
                Strategies\StaticData::withSettings(data: ['Custom-Header' => 'NotSoCustom']),
            ],
        ]);
        $this->enableResponseCalls();

        $this->generateAndExpectConsoleOutput(expected: [
            "Wrote Blade docs to: vendor/orchestra/testbench-core/laravel/resources/views/scribe",
            "Wrote Laravel assets to: vendor/orchestra/testbench-core/laravel/public/vendor/scribe",
            "Wrote OpenAPI specification to: vendor/orchestra/testbench-core/laravel/storage/app/scribe/openapi.yaml",
        ]);

        $generatedSpec = Yaml::parseFile($this->openapiOutputPath());
        $fixtureSpec = Yaml::parseFile(__DIR__ . '/../Fixtures/openapi.yaml');
        $this->assertEquals($fixtureSpec, $generatedSpec);
    }

    /** @test */
    public function generated_openapi_31_spec_file_is_correct()
    {
        if (phpversion() < 8.3) {
            // See https://github.com/FakerPHP/Faker/issues/694
            $this->markTestSkipped('Faker seeding changed in PHP 8.3');
        }

        RouteFacade::post('/api/withBodyParametersAsArray', [TestController::class, 'withBodyParametersAsArray']);
        RouteFacade::post('/api/withFormDataParams', [TestController::class, 'withFormDataParams']);
        RouteFacade::get('/api/withResponseTag', [TestController::class, 'withResponseTag']);
        RouteFacade::get('/api/withQueryParameters', [TestController::class, 'withQueryParameters']);
        RouteFacade::get('/api/withAuthTag', [TestController::class, 'withAuthenticatedTag']);
        RouteFacade::get('/api/echoesUrlParameters/{param}/{param2}/{param3?}/{param4?}', [TestController::class, 'echoesUrlParameters']);

        $this->setConfig([
            'openapi.enabled' => true,
            'openapi.version' => '3.1.0',
            'openapi.overrides' => [
                'info.version' => '3.9.9',
            ],
            'strategies.headers' =>  [
                ...config('scribe.strategies.headers'),
                Strategies\StaticData::withSettings(data: ['Custom-Header' => 'NotSoCustom']),
            ],
        ]);
        $this->enableResponseCalls();

        $this->generateAndExpectConsoleOutput(expected: [
            "Wrote Blade docs to: vendor/orchestra/testbench-core/laravel/resources/views/scribe",
            "Wrote Laravel assets to: vendor/orchestra/testbench-core/laravel/public/vendor/scribe",
            "Wrote OpenAPI specification to: vendor/orchestra/testbench-core/laravel/storage/app/scribe/openapi.yaml",
        ]);

        $generatedSpec = Yaml::parseFile($this->openapiOutputPath());
        $fixtureSpec = Yaml::parseFile(__DIR__ . '/../Fixtures/openapi-3_1.yaml');
        $this->assertEquals($fixtureSpec, $generatedSpec);
    }

    /** @test */
    public function can_parse_utf8_response()
    {
        RouteFacade::get('/api/utf8', [TestController::class, 'withUtf8ResponseTag']);

        $this->generate();

        $this->assertFileContainsString($this->bladeOutputPath(), 'Лорем ипсум долор сит амет');
    }

    /** @test */
    public function sorts_group_naturally_if_no_order_specified()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);
        RouteFacade::get('/api/action2', [TestGroupController::class, 'action2']);
        RouteFacade::get('/api/action10', [TestGroupController::class, 'action10']);

        $this->generate();

        $crawler = new Crawler(file_get_contents($this->bladeOutputPath()));
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
        $this->setConfig(['groups.order' => [
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

        $crawler = new Crawler(file_get_contents($this->bladeOutputPath()));
        $headings = $crawler->filter('h1')->getIterator();
        $this->assertCount(6, $headings); // intro, auth, four groups
        [$_, $_, $firstGroup, $secondGroup, $thirdGroup, $fourthGroup] = $headings;

        $this->assertEquals('10. Group 10', $firstGroup->textContent);
        $this->assertEquals('1. Group 1', $secondGroup->textContent);
        $this->assertEquals('13. Group 13', $thirdGroup->textContent);
        $this->assertEquals('2. Group 2', $fourthGroup->textContent);

        $firstGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($firstGroup->textContent) . '"]');
        $this->assertEquals(1, $firstGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action10", $firstGroupEndpointsAndSubgroups->getNode(0)->textContent);

        $secondGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($secondGroup->textContent) . '"]');
        $this->assertEquals(2, $secondGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action1b", $secondGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("GET api/action1", $secondGroupEndpointsAndSubgroups->getNode(1)->textContent);

        $thirdGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($thirdGroup->textContent) . '"]');
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
        $this->setConfig(['groups.order' => [
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

        $crawler = new Crawler(file_get_contents($this->bladeOutputPath()));
        $headings = $crawler->filter('h1')->getIterator();
        $this->assertCount(6, $headings); // intro, auth, four groups
        [$_, $_, $firstGroup, $secondGroup, $thirdGroup, $fourthGroup] = $headings;

        $this->assertEquals('10. Group 10', $firstGroup->textContent);
        $this->assertEquals('1. Group 1', $secondGroup->textContent);
        $this->assertEquals('2. Group 2', $thirdGroup->textContent);
        $this->assertEquals('13. Group 13', $fourthGroup->textContent);

        $firstGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($firstGroup->textContent) . '"]');
        $this->assertEquals(1, $firstGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action10", $firstGroupEndpointsAndSubgroups->getNode(0)->textContent);

        $secondGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($secondGroup->textContent) . '"]');
        $this->assertEquals(2, $secondGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action1", $secondGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("GET api/action1b", $secondGroupEndpointsAndSubgroups->getNode(1)->textContent);

        $fourthGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($fourthGroup->textContent) . '"]');
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
        $this->setConfig([
            'groups.order' => [
                '1. Group 1',
                '5. Group 5',
                '4. Group 4',
                '2. Group 2',
            ]
        ]);

        if (!is_dir('.scribe/endpoints')) mkdir('.scribe/endpoints', 0777, true);
        copy(__DIR__ . '/../Fixtures/custom.0.yaml', '.scribe/endpoints/custom.0.yaml');

        $this->generate();

        $crawler = new Crawler(file_get_contents($this->bladeOutputPath()));
        $headings = $crawler->filter('h1')->getIterator();
        $this->assertCount(6, $headings); // intro, auth, four groups
        [$_, $_, $firstGroup, $secondGroup, $thirdGroup, $fourthGroup] = $headings;

        $this->assertEquals('1. Group 1', $firstGroup->textContent);
        $this->assertEquals('5. Group 5', $secondGroup->textContent);
        $this->assertEquals('4. Group 4', $thirdGroup->textContent);
        $this->assertEquals('2. Group 2', $fourthGroup->textContent);

        $firstGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($firstGroup->textContent) . '"]');
        $this->assertEquals(2, $firstGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action1", $firstGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("User defined", $firstGroupEndpointsAndSubgroups->getNode(1)->textContent);

        $secondGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($secondGroup->textContent) . '"]');
        $this->assertEquals(2, $secondGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET group5", $secondGroupEndpointsAndSubgroups->getNode(0)->textContent);
        $this->assertEquals("GET alsoGroup5", $secondGroupEndpointsAndSubgroups->getNode(1)->textContent);

        $thirdGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($thirdGroup->textContent) . '"]');
        $this->assertEquals(1, $thirdGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET group4", $thirdGroupEndpointsAndSubgroups->getNode(0)->textContent);

        $fourthGroupEndpointsAndSubgroups = $crawler->filter('h2[id^="' . Str::slug($fourthGroup->textContent) . '"]');
        $this->assertEquals(1, $fourthGroupEndpointsAndSubgroups->count());
        $this->assertEquals("GET api/action2", $fourthGroupEndpointsAndSubgroups->getNode(0)->textContent);
    }

    /** @test */
    public function will_not_overwrite_manually_modified_content_unless_force_flag_is_set()
    {
        RouteFacade::get('/api/action1', [TestGroupController::class, 'action1']);
        RouteFacade::get('/api/action1b', [TestGroupController::class, 'action1b']);

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
            'exampleWasSpecified' => false,
            'nullable' => false,
            'deprecated' => false,
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
        $this->assertFileContainsString($authFilePath, 'Some other useful stuff.');

        $this->generate(['--force' => true]);

        $group = Yaml::parseFile($firstGroupFilePath);
        $this->assertEquals('api/action1', $group['endpoints'][0]['uri']);
        $this->assertEquals([], $group['endpoints'][0]['urlParameters']);
        $this->assertFileNotContainsString($authFilePath, 'Some other useful stuff.');
    }

    /** @test */
    public function generates_correct_url_params_from_resource_routes_and_field_bindings()
    {
        RouteFacade::prefix('providers/{provider:slug}')->group(function () {
            RouteFacade::resource('users.addresses', TestPartialResourceController::class)->parameters([
                'addresses' => 'address:uuid',
            ]);
        });

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

        $this->generate();

        $group = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('posts/{slug}', $group['endpoints'][0]['uri']);
    }

    /** @test */
    public function generates_correct_url_params_from_non_resource_routes_and_model_binding()
    {
        RouteFacade::get('posts/{post}/users', fn (TestPost $post) => null);

        $this->generate();

        $group = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('posts/{post_slug}/users', $group['endpoints'][0]['uri']);
    }

    /** @test */
    public function generates_from_camel_dir_if_noExtraction_flag_is_set()
    {
        $this->setConfig(['routes.0.exclude' => ['*']]);
        Utils::copyDirectory(__DIR__ . '/../Fixtures/.scribe', '.scribe');

        $this->generateAndExpectConsoleOutput(['--no-extraction' => true], notExpected: ["Processing route"]);

        $crawler = new Crawler(file_get_contents($this->bladeOutputPath()));
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

        $this->generate();

        $group = Yaml::parseFile('.scribe/endpoints/00.yaml');
        $this->assertEquals('no-file', $group['endpoints'][0]['uri']);
        $this->assertEquals('application/json', $group['endpoints'][0]['headers']['Content-Type']);
        $this->assertEquals('top-level-file', $group['endpoints'][1]['uri']);
        $this->assertEquals('multipart/form-data', $group['endpoints'][1]['headers']['Content-Type']);
        $this->assertEquals('nested-file', $group['endpoints'][2]['uri']);
        $this->assertEquals('multipart/form-data', $group['endpoints'][2]['headers']['Content-Type']);

    }

    /** @test */
    public function html_special_characters_in_json_body_are_properly_escaped_in_elements_theme()
    {
        RouteFacade::post('/api/withHtmlSpecialCharsInBody', [TestController::class, 'withHtmlSpecialCharsInBody']);
        
        $this->setConfig([
            'type' => 'laravel',
            'theme' => 'elements',
        ]);
        
        $this->generate();
        
        $bladeContent = file_get_contents($this->bladeOutputPath());
        
        // Check that the JSON body doesn't contain raw HTML special characters, which can break the rest of the containing HTML file when rendered
        // The < and > should be escaped as \u003C and \u003E
        // The & should be escaped as \u0026
        $this->assertStringContainsString('\\u003C', $bladeContent);
        $this->assertStringContainsString('\\u003E', $bladeContent);
        $this->assertStringContainsString('\\u0026', $bladeContent);
        
        // Verify the actual JSON contains the expected escaped values
        $this->assertStringContainsString('"username": "user\\u003Ctest\\u003E"', $bladeContent);
        $this->assertStringContainsString('"password": "pass\\u0026word\\u003C123\\u003E"', $bladeContent);
    }

    /** @test */
    public function generated_openapi_spec_correctly_handles_nested_api_resource_required_fields()
    {
        RouteFacade::get('/api/nested-resource', [TestController::class, 'withNestedApiResourceResponse']);

        $this->setConfig([
            'openapi.enabled' => true,
        ]);

        $this->generate();

        $generatedSpec = Yaml::parseFile($this->openapiOutputPath());

        $responseSchema = $generatedSpec['paths']['/api/nested-resource']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertArrayHasKey('properties', $responseSchema);
        $this->assertArrayHasKey('data', $responseSchema['properties']);
        $dataSchema = $responseSchema['properties']['data'];

        // Verify 'outer1' and 'outer2' are in data properties
        $this->assertArrayHasKey('properties', $dataSchema);
        $this->assertArrayHasKey('outer1', $dataSchema['properties']);
        $this->assertArrayHasKey('outer2', $dataSchema['properties']);

        // Verify both are marked as required at the data level
        $this->assertArrayHasKey('required', $dataSchema);
        $this->assertContains('outer1', $dataSchema['required']);
        $this->assertContains('outer2', $dataSchema['required']);

        // Verify 'outer1.inner1' is nested correctly and marked as required
        $outer1Schema = $dataSchema['properties']['outer1'];
        $this->assertEquals('object', $outer1Schema['type']);
        $this->assertArrayHasKey('properties', $outer1Schema);
        $this->assertArrayHasKey('inner1', $outer1Schema['properties']);
        $this->assertArrayHasKey('required', $outer1Schema);
        $this->assertContains('inner1', $outer1Schema['required']);

        // Verify 'outer2.inner2' is nested correctly and marked as required
        $outer2Schema = $dataSchema['properties']['outer2'];
        $this->assertEquals('object', $outer2Schema['type']);
        $this->assertArrayHasKey('properties', $outer2Schema);
        $this->assertArrayHasKey('inner2', $outer2Schema['properties']);
        $this->assertArrayHasKey('required', $outer2Schema);
        $this->assertContains('inner2', $outer2Schema['required']);
    }

    protected function postmanOutputPath(bool $staticType = false): string
    {
        return $staticType
            ? 'public/docs/collection.json' : Storage::disk('local')->path('scribe/collection.json');
    }

    protected function openapiOutputPath(bool $staticType = false): string
    {
        return $staticType
            ? 'public/docs/openapi.yaml' : Storage::disk('local')->path('scribe/openapi.yaml');
    }

    protected function htmlOutputPath(): string
    {
        return 'public/docs/index.html';
    }

    protected function bladeOutputPath(): string
    {
        return View::getFinder()->find('scribe/index');
    }

    protected function enableResponseCalls(): void
    {
        $this->setConfig([
            'strategies.responses' => configureStrategy(
                config('scribe.strategies.responses'),
                Strategies\Responses\ResponseCalls::withSettings(only: ['GET *'], except: [])
            )
        ]);
    }
}
