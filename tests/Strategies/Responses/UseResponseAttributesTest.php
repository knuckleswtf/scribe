<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Database\Eloquent\LegacyFactoryServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\ResponseFromFile;
use Knuckles\Scribe\Attributes\ResponseFromTransformer;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseAttributes;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestModel;
use Knuckles\Scribe\Tests\Fixtures\TestPet;
use Knuckles\Scribe\Tests\Fixtures\TestTransformer;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tests\Fixtures\TestUserApiResource;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Utils;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * @internal
 *
 * @coversNothing
 */
class UseResponseAttributesTest extends BaseLaravelTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig(['database_connections_to_transact' => []]);

        $factory = app(Factory::class);
        $factory->define(TestUser::class, function () {
            return [
                'id' => 4,
                'first_name' => 'Tested',
                'last_name' => 'Again',
                'email' => 'a@b.com',
            ];
        });
        $factory->state(TestUser::class, 'state1', ['state1' => true]);
        $factory->state(TestUser::class, 'random-state', ['random-state' => true]);
        $factory->define(TestPet::class, function () {
            return [
                'id' => 1,
                'name' => 'Mephistopheles',
                'species' => 'dog',
            ];
        });
    }

    /** @test */
    public function can_parse_plain_response_attributes()
    {
        $results = $this->fetch($this->endpoint('plainResponseAttributes'));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode(['all' => 'good']),
                'description' => 'Success',
            ],
            [
                'status' => 201,
                'content' => json_encode(['all' => 'good']),
            ],
            [
                'status' => 404,
                'content' => null,
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_responsefile_attributes()
    {
        $results = $this->fetch($this->endpoint('responseFileAttributes'));

        $this->assertArraySubset([
            [
                'status' => 401,
                'content' => json_encode(['message' => 'Unauthorized', 'merge' => 'this']),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresource_attributes()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));
            }
        });

        $results = $this->fetch($this->endpoint('apiResourceAttributes'));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                            'children' => [
                                [
                                    'id' => 5,
                                    'name' => 'Tested Again',
                                    'email' => 'a@b.com',
                                ],
                            ],
                            'state1' => true,
                            'random-state' => true,
                        ],
                    ],
                    'links' => [
                        'first' => '/?page=1',
                        'last' => null,
                        'prev' => null,
                        'next' => '/?page=2',
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'from' => 1,
                        'path' => '/',
                        'per_page' => 1,
                        'to' => 1,
                    ],
                    'a' => 'b',
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresource_attributes_with_no_model_specified()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));
            }
        });

        $results = $this->fetch($this->endpoint('apiResourceAttributesWithNoModel'));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                            'children' => [
                                [
                                    'id' => 5,
                                    'name' => 'Tested Again',
                                    'email' => 'a@b.com',
                                ],
                            ],
                            'state1' => true,
                            'random-state' => true,
                        ],
                    ],
                    'links' => [
                        'first' => '/?page=1',
                        'last' => null,
                        'prev' => null,
                        'next' => '/?page=2',
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'from' => 1,
                        'path' => '/',
                        'per_page' => 1,
                        'to' => 1,
                    ],
                    'a' => 'b',
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_transformer_attributes()
    {
        $results = $this->fetch($this->endpoint('transformerAttributes'));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        [
                            'id' => 1,
                            'description' => 'Welcome on this test versions',
                            'name' => 'TestName',
                        ],
                    ],
                    'meta' => [
                        'pagination' => [
                            'total' => 2,
                            'count' => 1,
                            'per_page' => 1,
                            'current_page' => 1,
                            'total_pages' => 2,
                            'links' => ['next' => '/?page=2'],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresource_attributes_with_cursor_pagination()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));
            }
        });

        $results = $this->fetch($this->endpoint('apiResourceAttributesWithCursorPaginate'));

        $nextCursor = base64_encode(json_encode(['_pointsToNextItems' => true]));
        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                            'children' => [
                                [
                                    'id' => 5,
                                    'name' => 'Tested Again',
                                    'email' => 'a@b.com',
                                ],
                            ],
                        ],
                    ],
                    'links' => [
                        'first' => null,
                        'last' => null,
                        'prev' => null,
                        'next' => "/?cursor={$nextCursor}",
                    ],
                    'meta' => [
                        'path' => '/',
                        'per_page' => 1,
                        'next_cursor' => $nextCursor,
                        'prev_cursor' => null,
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresource_attributes_and_load_children_using_factory_create()
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->integer('parent_id')->nullable();
        });

        $factory = app(Factory::class);
        $factory->afterCreating(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                Utils::getModelFactory(TestUser::class)->create(['id' => 5, 'parent_id' => 4]);
            }
        });
        $documentationConfig = ['examples' => ['models_source' => ['factoryCreate']]];

        $results = $this->fetch($this->endpoint('apiResourceAttributesIncludeChildren'), $documentationConfig);
        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 4,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                        'children' => [
                            [
                                'id' => 5,
                                'name' => 'Tested Again',
                                'email' => 'a@b.com',
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /**
     * @test
     *
     * @testWith ["factoryCreate", true]
     *           ["factoryCreateQuietly", false]
     */
    public function it_can_suppress_model_events_using_factory_create_quietly(string $modelFactoryStrategy, bool $expectModelEvents)
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->integer('parent_id')->nullable();
        });

        $modelEventFired = false;
        TestUser::creating(function () use (&$modelEventFired) {
            $modelEventFired = true;
        });
        $documentationConfig = ['examples' => ['models_source' => [$modelFactoryStrategy]]];

        $results = $this->fetch($this->endpoint('apiResourceAttributesIncludeChildren'), $documentationConfig);
        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 4,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                        'children' => [],
                    ],
                ]),
            ],
        ], $results);
        $this->assertSame($expectModelEvents, $modelEventFired);
    }

    /** @test */
    public function can_parse_apiresource_attributes_and_load_children_and_children_count_using_factory_create()
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->integer('parent_id')->nullable();
        });

        $factory = app(Factory::class);
        $factory->afterCreating(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                Utils::getModelFactory(TestUser::class)->create(['id' => 5, 'parent_id' => 4]);
            }
        });
        $documentationConfig = ['examples' => ['models_source' => ['factoryCreate']]];

        $results = $this->fetch($this->endpoint('apiResourceAttributesIncludeChildrenAndChildrenCount'), $documentationConfig);
        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 4,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                        'children' => [
                            [
                                'id' => 5,
                                'name' => 'Tested Again',
                                'email' => 'a@b.com',
                            ],
                        ],
                        'children_count' => 1,
                    ],
                ]),
            ],
        ], $results);
    }

    protected function getPackageProviders($app)
    {
        $providers = parent::getPackageProviders($app);
        if (class_exists(LegacyFactoryServiceProvider::class)) {
            $providers[] = LegacyFactoryServiceProvider::class;
        }

        return $providers;
    }

    protected function fetch($endpoint, array $documentationConfig = []): array
    {
        $strategy = new UseResponseAttributes(new DocumentationConfig($documentationConfig));

        return $strategy($endpoint, []);
    }

    protected function endpoint(string $method): ExtractedEndpointData
    {
        $endpoint = new class extends ExtractedEndpointData
        {
            public function __construct(array $parameters = []) {}
        };
        $endpoint->controller = new \ReflectionClass(ResponseAttributesTestController::class);
        $endpoint->method = $endpoint->controller->getMethod($method);
        $endpoint->route = new Route(['POST'], '/somethingRandom', ['uses' => [ResponseAttributesTestController::class, $method]]);

        return $endpoint;
    }
}

class ResponseAttributesTestController
{
    #[Response(['all' => 'good'], 200, 'Success')]
    #[Response('{"all":"good"}', 201)]
    #[Response(status: 404)]
    public function plainResponseAttributes() {}

    #[ResponseFromFile('tests/Fixtures/response_error_test.json', 401, ['merge' => 'this'])]
    public function responseFileAttributes() {}

    #[ResponseFromApiResource(
        TestUserApiResource::class,
        TestUser::class,
        collection: true,
        factoryStates: ['state1', 'random-state'],
        simplePaginate: 1,
        additional: ['a' => 'b']
    )]
    public function apiResourceAttributes() {}

    #[ResponseFromApiResource(
        TestUserApiResource::class,
        collection: true,
        factoryStates: ['state1', 'random-state'],
        simplePaginate: 1,
        additional: ['a' => 'b']
    )]
    public function apiResourceAttributesWithNoModel() {}

    #[ResponseFromTransformer(
        TestTransformer::class,
        TestModel::class,
        collection: true,
        paginate: [IlluminatePaginatorAdapter::class, 1]
    )]
    public function transformerAttributes() {}

    #[ResponseFromApiResource(TestUserApiResource::class, collection: true, cursorPaginate: 1)]
    public function apiResourceAttributesWithCursorPaginate() {}

    #[ResponseFromApiResource(TestUserApiResource::class, with: ['children'], withCount: ['children'])]
    public function apiResourceAttributesIncludeChildrenAndChildrenCount() {}

    #[ResponseFromApiResource(TestUserApiResource::class, with: ['children'])]
    public function apiResourceAttributesIncludeChildren() {}
}
