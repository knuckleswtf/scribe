<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseAttributes;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tests\Fixtures\TestEmptyJsonApiResource;
use Knuckles\Scribe\Tests\Fixtures\TestPost;
use Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Laravel's JSON:API resources (`Illuminate\Http\Resources\JsonApi`), added in 12.45.
 *
 * @internal
 *
 * @coversNothing
 */
class JsonApiResourceTest extends BaseLaravelTest
{
    /**
     * Both types are derived by the framework from the resource class name.
     */
    private const POST_TYPE = 'test_post_json_apis';

    private const TAG_TYPE = 'test_tag_json_apis';

    protected function setUp(): void
    {
        parent::setUp();

        // Must come before anything touches a fixture extending JsonApiResource.
        $this->skipIfNoJsonApiResources();

        $this->setConfig(['database_connections_to_transact' => []]);
        $this->createSchema();
    }

    public function test_does_not_include_relationships_the_example_model_was_not_loaded_with()
    {
        $content = $this->getContent([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost'),
        ]);

        $this->assertEquals([
            'id' => '1',
            'type' => self::POST_TYPE,
            'attributes' => ['title' => 'Test title', 'body' => 'random body'],
        ], $content['data']);
        $this->assertArrayNotHasKey('relationships', $content['data']);
        $this->assertArrayNotHasKey('included', $content);
    }

    public function test_includes_the_relationships_the_example_model_was_loaded_with()
    {
        $content = $this->getContent([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ]);

        $this->assertEquals(
            ['tags' => ['data' => [['id' => '1', 'type' => self::TAG_TYPE]]]],
            $content['data']['relationships']
        );
        $this->assertEquals(
            [['id' => '1', 'type' => self::TAG_TYPE, 'attributes' => ['name' => 'tag 1']]],
            $content['included']
        );
    }

    public function test_reads_relationships_and_attributes_declared_as_properties()
    {
        $content = $this->getContent([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResourceWithProperties'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ]);

        // Both are declared with int keys (`['tags']`), so the name lives in the value.
        $this->assertEquals(['title' => 'Test title', 'body' => 'random body'], $content['data']['attributes']);
        // No resource class was given for the relationship, so the framework types it off the model.
        $this->assertEquals(['tags' => ['data' => [['id' => '1', 'type' => 'test_tags']]]], $content['data']['relationships']);
        $this->assertCount(1, $content['included']);
    }

    public function test_includes_relationships_in_collections()
    {
        $content = $this->getContent([
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ]);

        $this->assertCount(2, $content['data']);
        foreach ($content['data'] as $item) {
            $this->assertEquals(['tags' => ['data' => [['id' => '1', 'type' => self::TAG_TYPE]]]], $item['relationships']);
        }
        $this->assertNotEmpty($content['included']);
    }

    public function test_includes_relationships_in_paginated_collections()
    {
        $content = $this->getContent([
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags paginate=1'),
        ]);

        $this->assertCount(1, $content['data']);
        $this->assertEquals(['tags' => ['data' => [['id' => '1', 'type' => self::TAG_TYPE]]]], $content['data'][0]['relationships']);
        $this->assertNotEmpty($content['included']);
        $this->assertArrayHasKey('links', $content);
        $this->assertArrayHasKey('meta', $content);
    }

    public function test_keeps_additional_data_alongside_included()
    {
        $content = $this->getContent([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
            new Tag('apiResourceAdditional', 'a=b'),
        ]);

        $this->assertEquals('b', $content['a']);
        $this->assertNotEmpty($content['included']);
    }

    public function test_documents_the_json_api_content_type_of_the_response()
    {
        $results = $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost'),
        ]);

        $this->assertEquals(['content-type' => 'application/vnd.api+json'], $results[0]['headers']);
    }

    public function test_documents_the_include_query_parameter()
    {
        $endpointData = $this->makeEndpointData();
        $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ], $endpointData);

        $include = $endpointData->queryParameters['include'];
        $this->assertStringContainsString('`tags`', $include->description);
        $this->assertStringContainsString('dot notation', $include->description);
        $this->assertEmpty($include->enumValues);
        $this->assertEquals('tags', $include->example);
        $this->assertEquals('tags', $endpointData->cleanQueryParameters['include']);
    }

    public function test_does_not_give_the_include_query_parameter_an_example_when_no_relations_were_loaded()
    {
        $endpointData = $this->makeEndpointData();
        $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost'),
        ], $endpointData);

        // Documented in the parameters table, but left out of the example requests, so those
        // stay consistent with the example response, which has no relationships in it.
        $this->assertStringContainsString('`tags`', $endpointData->queryParameters['include']->description);
        $this->assertNull($endpointData->queryParameters['include']->example);
        $this->assertArrayNotHasKey('include', $endpointData->cleanQueryParameters);
    }

    public function test_documents_the_sparse_fieldset_query_parameter()
    {
        $endpointData = $this->makeEndpointData();
        $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost'),
        ], $endpointData);

        $name = 'fields['.self::POST_TYPE.']';
        $this->assertStringContainsString('`title`, `body`', $endpointData->queryParameters[$name]->description);
        // No example: unlike `include`, a sparse fieldset doesn't change the example response,
        // so it stays out of the example requests (same rule the package's `No-example` uses).
        $this->assertNull($endpointData->queryParameters[$name]->example);
        $this->assertArrayNotHasKey('fields', $endpointData->cleanQueryParameters);
    }

    public function test_documents_a_sparse_fieldset_for_the_types_of_relationships_that_were_loaded()
    {
        $endpointData = $this->makeEndpointData();
        $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ], $endpointData);

        // Here the related type is the one the framework derives from the resource class name;
        // an overridden `toType()` is covered by the nested test below.
        $name = 'fields['.self::TAG_TYPE.']';
        $this->assertArrayHasKey($name, $endpointData->queryParameters);
        $this->assertStringContainsString('`name`', $endpointData->queryParameters[$name]->description);
        $this->assertNull($endpointData->queryParameters[$name]->example);
    }

    public function test_documents_a_sparse_fieldset_for_every_type_in_a_nested_relation()
    {
        $this->createOrderSchema();

        $endpointData = $this->makeEndpointData();
        $results = $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestOrderOwnerJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestOrderOwner with=order.status'),
        ], $endpointData);

        $content = json_decode($results[0]['content'], true);
        $this->assertEquals(['orders', 'order_statuses'], array_column($content['included'], 'type'));

        // One fieldset per type in that response: the owner, its order, and the order's status.
        $this->assertEquals([
            'include',
            'fields[order_owners]',
            'fields[orders]',
            'fields[order_statuses]',
        ], array_keys($endpointData->queryParameters));
        $this->assertStringContainsString(
            '`name`', $endpointData->queryParameters['fields[order_statuses]']->description
        );

        $this->assertEquals('order.status', $endpointData->queryParameters['include']->example);
        $this->assertStringContainsString('`order`', $endpointData->queryParameters['include']->description);
    }

    public function test_does_not_document_a_sparse_fieldset_for_relationships_that_were_not_loaded()
    {
        $endpointData = $this->makeEndpointData();
        $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost'),
        ], $endpointData);

        // Without the relation loaded there's no model to read the related attribute names off,
        // and the relationship isn't in the example response either.
        $this->assertArrayHasKey('fields['.self::POST_TYPE.']', $endpointData->queryParameters);
        $this->assertArrayNotHasKey('fields['.self::TAG_TYPE.']', $endpointData->queryParameters);
    }

    public function test_does_not_overwrite_query_parameters_the_user_documented()
    {
        $endpointData = $this->makeEndpointData();
        $endpointData->queryParameters['include'] = new \Knuckles\Camel\Extraction\Parameter([
            'name' => 'include', 'description' => 'Mine', 'example' => 'mine',
        ]);

        $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ], $endpointData);

        $this->assertEquals('Mine', $endpointData->queryParameters['include']->description);
        $this->assertEquals('mine', $endpointData->queryParameters['include']->example);
    }

    public function test_can_turn_off_query_parameter_documentation()
    {
        $endpointData = $this->makeEndpointData();
        $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ], $endpointData, ['json_api' => ['document_query_parameters' => false]]);

        $this->assertEmpty($endpointData->queryParameters);
    }

    public function test_warns_instead_of_crashing_when_there_is_no_model_for_the_resource()
    {
        // A JSON:API response needs an id, and Scribe passes an empty array when it can't find a
        // model, which used to blow up with a TypeError — an Error, not an Exception, so it took
        // the whole generation down instead of just this endpoint.
        // See https://github.com/knuckleswtf/scribe/issues/652.
        $boundRequest = app('request');
        $results = $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestEmptyJsonApiResource'),
        ]);

        $this->assertNull($results);
        // Rendering threw halfway through, so the request we bound for it must still be unbound,
        // or every endpoint extracted after this one would be handed it.
        $this->assertSame($boundRequest, app('request'));
    }

    public function test_does_not_touch_regular_api_resources()
    {
        $endpointData = $this->makeEndpointData();
        $results = $this->fetch([
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ], $endpointData);

        $this->assertEquals(json_encode([
            'data' => [
                'id' => 1,
                'title' => 'Test title',
                'body' => 'random body',
                'tags' => [['id' => 1, 'name' => 'tag 1', 'priority' => 'high']],
            ],
        ]), $results[0]['content']);
        $this->assertEmpty($results[0]['headers']);
        $this->assertEmpty($endpointData->queryParameters);
        $this->assertEmpty($endpointData->headers);
    }

    public function test_supports_the_response_from_api_resource_attribute()
    {
        $endpointData = $this->attributeEndpoint('postWithTags');
        $results = (new UseResponseAttributes(new DocumentationConfig))($endpointData, []);

        $content = json_decode($results[0]['content'], true);
        $this->assertEquals(
            ['tags' => ['data' => [['id' => '1', 'type' => self::TAG_TYPE]]]],
            $content['data']['relationships']
        );
        $this->assertNotEmpty($content['included']);
        // `additional()` data is merged in alongside `included`, not swallowed by it.
        $this->assertEquals(['total' => 10], $content['meta']);
        $this->assertEquals(['content-type' => 'application/vnd.api+json'], $results[0]['headers']);
        $this->assertEquals('tags', $endpointData->queryParameters['include']->example);
    }

    public function test_the_response_from_api_resource_attribute_warns_when_there_is_no_model()
    {
        $results = (new UseResponseAttributes(new DocumentationConfig))($this->attributeEndpoint('noModel'), []);

        $this->assertEmpty($results);
    }

    /**
     * @param  Tag[]  $tags
     */
    protected function fetch(array $tags, ?ExtractedEndpointData $endpointData = null, array $config = []): ?array
    {
        $strategy = new UseApiResourceTags(new DocumentationConfig($config));

        return $strategy->getApiResourceResponseFromTags(
            $strategy->getApiResourceTag($tags), $tags, $endpointData ?: $this->makeEndpointData()
        );
    }

    /**
     * @param  Tag[]  $tags
     */
    protected function getContent(array $tags): array
    {
        return json_decode($this->fetch($tags)[0]['content'], true);
    }

    protected function attributeEndpoint(string $method): ExtractedEndpointData
    {
        $endpoint = new class extends ExtractedEndpointData
        {
            public function __construct(array $parameters = []) {}
        };
        $endpoint->controller = new \ReflectionClass(JsonApiAttributesTestController::class);
        $endpoint->method = $endpoint->controller->getMethod($method);
        $endpoint->route = new Route(['GET'], '/somethingRandom', ['uses' => [JsonApiAttributesTestController::class, $method]]);

        return $endpoint;
    }

    protected function makeEndpointData(): ExtractedEndpointData
    {
        return ExtractedEndpointData::fromRoute(
            new Route(['GET'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']])
        );
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

    /**
     * An owner -> order -> status chain, for the tests that need a nested relation.
     */
    protected function createOrderSchema(): void
    {
        Schema::create('test_order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('test_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->nullable();
            $table->foreignId('delivery_id')->nullable();
        });

        Schema::create('test_order_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('status_id')->nullable();
        });
    }
}

/**
 * Only ever instantiated from tests that skipped themselves if JSON:API resources are unavailable.
 * The resource class names below are `::class` constants, so nothing here autoloads on older versions.
 */
class JsonApiAttributesTestController
{
    #[ResponseFromApiResource(
        TestPostJsonApiResource::class,
        TestPost::class,
        with: ['tags'],
        additional: ['meta' => ['total' => 10]],
    )]
    public function postWithTags() {}

    #[ResponseFromApiResource(TestEmptyJsonApiResource::class)]
    public function noModel() {}
}
