<?php

namespace Knuckles\Scribe\Tests\GenerateDocumentation;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestJsonApiController;
use Knuckles\Scribe\Tests\TestHelpers;
use Knuckles\Scribe\Tools\Utils;
use Symfony\Component\Yaml\Yaml;

/**
 * End-to-end output for Laravel's JSON:API resources (12.45+).
 *
 * @internal
 *
 * @coversNothing
 */
class JsonApiOutputTest extends BaseLaravelTest
{
    use TestHelpers;

    private const POST_TYPE = 'test_post_json_apis';

    private const TAG_TYPE = 'test_tag_json_apis';

    protected function setUp(): void
    {
        parent::setUp();

        // Must come before anything touches a fixture extending JsonApiResource.
        $this->skipIfNoJsonApiResources();

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Utils::deleteDirectoryAndContents('public/docs');
        Utils::deleteDirectoryAndContents('.scribe');

        parent::tearDown();
    }

    public function test_documents_the_json_api_content_type_and_query_parameters_in_the_openapi_spec()
    {
        RouteFacade::get('/api/posts/{id}', [TestJsonApiController::class, 'showPost']);
        $this->setConfig(['openapi.enabled' => true]);

        $this->generate();

        $operation = Yaml::parseFile($this->openapiOutputPath())['paths']['/api/posts/{id}']['get'];

        $this->assertArrayHasKey(
            'application/vnd.api+json',
            $operation['responses'][200]['content']
        );

        $parameters = collect($operation['parameters'])->keyBy('name');

        $this->assertStringContainsString('`tags`', $parameters['include']['description']);
        $this->assertArrayNotHasKey('enum', $parameters['include']['schema']);
        $this->assertEquals('tags', $parameters['include']['example']);

        $this->assertStringContainsString('`title`, `body`', $parameters['fields['.self::POST_TYPE.']']['description']);
    }

    public function test_sends_the_include_parameter_in_example_requests()
    {
        RouteFacade::get('/api/posts/{id}', [TestJsonApiController::class, 'showPost']);

        $this->generate();

        $this->assertFileContainsString($this->bladeOutputPath(), '?include=tags"');
        $this->assertFileContainsString($this->bladeOutputPath(), '--header "Accept: application/vnd.api+json"');
    }

    public function test_documents_a_sparse_fieldset_per_resource_type_in_the_parameters_table()
    {
        RouteFacade::get('/api/posts/{id}', [TestJsonApiController::class, 'showPost']);

        $this->generate();

        // The main resource, plus the resource of the relationship the example model was loaded with.
        $this->assertFileContainsString($this->bladeOutputPath(), 'fields['.self::POST_TYPE.']');
        $this->assertFileContainsString($this->bladeOutputPath(), 'fields['.self::TAG_TYPE.']');
    }

    public function test_does_not_take_the_whole_generation_down_when_a_resource_has_no_model()
    {
        RouteFacade::get('/api/broken', [TestJsonApiController::class, 'resourceWithoutAModel']);
        RouteFacade::get('/api/posts/{id}', [TestJsonApiController::class, 'showPost']);

        $output = $this->generate();

        $this->assertStringContainsString("Couldn't render your JSON:API resource", $output);
        // The other endpoint still made it into the docs.
        $this->assertFileContainsString($this->bladeOutputPath(), 'A JSON:API post');
    }

    protected function openapiOutputPath(): string
    {
        return Storage::disk('local')->path('scribe/openapi.yaml');
    }

    protected function bladeOutputPath(): string
    {
        return \Illuminate\Support\Facades\View::getFinder()->find('scribe/index');
    }

    protected function createSchema(): void
    {
        Schema::create('test_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('body');
            $table->timestamps();
        });

        Schema::create('test_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->string('test_tag_id');
            $table->string('taggable_type');
            $table->string('taggable_id');
            $table->string('priority');
        });
    }
}
