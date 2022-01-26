<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\inmemory\TestWork as InMemoryTestWork;
use Knuckles\Scribe\Tests\Fixtures\inmemory\TestPet as InMemoryTestPet;
use Knuckles\Scribe\Tests\Fixtures\inmemory\TestUser as InmemoryTestUser;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tests\Fixtures\TestPet;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class UseApiResourceTagsTest extends BaseLaravelTest
{
    use ArraySubsetAsserts;

    protected function getPackageProviders($app)
    {
        $providers = [
            ScribeServiceProvider::class,
        ];
        if (class_exists(\Dingo\Api\Provider\LaravelServiceProvider::class)) {
            $providers[] = \Dingo\Api\Provider\LaravelServiceProvider::class;
        }
        if (class_exists(\Illuminate\Database\Eloquent\LegacyFactoryServiceProvider::class)) {
            $providers[] = \Illuminate\Database\Eloquent\LegacyFactoryServiceProvider ::class;
        }
        return $providers;
    }

    public function setUp(): void
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
        $factory->state(TestUser::class, 'state1', ["state1" => true]);
        $factory->state(TestUser::class, 'random-state', ["random-state" => true]);
        $factory->define(TestPet::class, function () {
            return [
                'id' => 1,
                'name' => 'Mephistopheles',
                'species' => 'dog',
            ];
        });
    }

    /** @test */
    public function can_parse_apiresource_tags()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 4,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function properly_binds_route_and_request_when_fetching_apiresource_response()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);
        $route->name('someone');

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'someone' => true,
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresource_tags_with_model_factory_states()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '201 \Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser states=state1,random-state'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 201,
                'content' => json_encode([
                    'data' => [
                        'id' => 4,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                        'state1' => true,
                        'random-state' => true,
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loads_specified_relations_for_model()
    {
        $factory = app(\Illuminate\Database\Eloquent\Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));
            }
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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

    /** @test */
    public function loads_specified_relations_for_generated_model()
    {
        $factory = app(\Illuminate\Database\Eloquent\Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));
            }
        });
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=children'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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

    /** @test */
    public function loads_specified_nested_relations_for_generated_model()
    {
        $factory = app(\Illuminate\Database\Eloquent\Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));

                $grandchild = Utils::getModelFactory(TestUser::class)->make(['id' => 6, 'parent_id' => 5]);
                $child->setRelation('children', collect([$grandchild]));
            }
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=children.children'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                                'children' => [
                                    [
                                        'id' => 6,
                                        'name' => 'Tested Again',
                                        'email' => 'a@b.com',
                                    ]
                                ]
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loads_specified_many_to_many_relations_for_generated_model()
    {
        $factory = app(\Illuminate\Database\Eloquent\Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            $pet = Utils::getModelFactory(TestPet::class)->make(['id' => 1]);
            $user->setRelation('pets', collect([$pet]));
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=pets'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 4,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                        'pets' => [
                            [
                                'id' => 1,
                                'name' => 'Mephistopheles',
                                'species' => 'dog'
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loads_specified_many_to_many_and_nested_relations_for_generated_model()
    {
        $factory = app(\Illuminate\Database\Eloquent\Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if ($user->id === 4) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));

                $pet = Utils::getModelFactory(TestPet::class)->make(['id' => 1]);
                $child->setRelation('pets', collect([$pet]));
            }
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=children.pets'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                                'pets' => [
                                    [
                                        'id' => 1,
                                        'name' => 'Mephistopheles',
                                        'species' => 'dog'
                                    ],
                                ],
                            ]
                        ]

                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loads_specified_many_to_many_and_nested_relations_for_generated_model_with_inmemory_db()
    {
        $this->setUpInmemory();

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\inmemory\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\inmemory\TestUser with=work.departments,children.pets'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 1,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                        'children' => [
                            [
                                'id' => 2,
                                'name' => 'Tested Again',
                                'email' => 'a@b.com',
                                'pets' => [
                                    [
                                        'id' => 1,
                                        'name' => 'Mephistopheles',
                                        'species' => 'dog'
                                    ],
                                ],
                            ]
                        ],
                        'work' => [
                            'id' => 1,
                            'name' => 'My best work',
                            'departments' => [
                                [
                                    'id' => 1,
                                    'name' => 'My best department',
                                ]
                            ],
                        ]
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loads_specified_many_to_many_relations_for_generated_model_with_pivot()
    {
        $factory = app(\Illuminate\Database\Eloquent\Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            $pet = Utils::getModelFactory(TestPet::class)->make(['id' => 1]);

            $pivot = $pet->newPivot($user, [
                'pet_id' => $pet->id,
                'user_id' => $user->id,
                'duration' => 2
            ], 'pet_user', true);

            $pet->setRelation('pivot', $pivot);

            $user->setRelation('pets', collect([$pet]));
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=pets'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 4,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                        'pets' => [
                            [
                                'id' => 1,
                                'name' => 'Mephistopheles',
                                'species' => 'dog',
                                'ownership' => [
                                    'pet_id' => 1,
                                    'user_id' => 4,
                                    'duration' => 2
                                ]
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresourcecollection_tags()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                        ],
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresourcecollection_tags_with_collection_class()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResourceCollection'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                        ],
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                        ],
                    ],
                    'links' => [
                        'self' => 'link-value',
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresourcecollection_tags_with_collection_class_and_pagination()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResourceCollection'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser paginate=1,simple'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                        ],
                    ],
                    'links' => [
                        'self' => 'link-value',
                        "first" => '/?page=1',
                        "last" => null,
                        "prev" => null,
                        "next" => '/?page=2',
                    ],
                    "meta" => [
                        "current_page" => 1,
                        "from" => 1,
                        "path" => '/',
                        "per_page" => "1",
                        "to" => 1,
                    ],
                ]),
            ],
        ], $results);
    }

    public function dataResources()
    {
        return [
            [
                null,
                '{"data":{"id":1,"description":"Welcome on this test versions","name":"TestName"}}',
            ],
            [
                'League\Fractal\Serializer\JsonApiSerializer',
                '{"data":{"type":null,"id":"1","attributes":{"description":"Welcome on this test versions","name":"TestName"}}}',
            ],
        ];
    }

    /** @test */
    public function can_parse_apiresourceadditional_tags()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
            new Tag('apiResourceAdditional', 'a=b "custom field"=c e="custom value" "another field"="true value"')
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 4,
                        'name' => 'Tested Again',
                        'email' => 'a@b.com',
                    ],
                    'a' => 'b',
                    'custom field' => 'c',
                    'e' => 'custom value',
                    'another field' => 'true value',
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_apiresourcecollection_tags_with_collection_class_pagination_and_apiresourceadditional_tag()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], "/somethingRandom", ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResourceCollection'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser paginate=1,simple'),
            new Tag('apiResourceAdditional', 'a=b'),
        ];
        $results = $strategy->getApiResourceResponse($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        [
                            'id' => 4,
                            'name' => 'Tested Again',
                            'email' => 'a@b.com',
                        ],
                    ],
                    'links' => [
                        'self' => 'link-value',
                        "first" => '/?page=1',
                        "last" => null,
                        "prev" => null,
                        "next" => '/?page=2',
                    ],
                    'meta' => [
                        "current_page" => 1,
                        "from" => 1,
                        "path" => '/',
                        "per_page" => "1",
                        "to" => 1,
                    ],
                    'a' => 'b',
                ]),
            ],
        ], $results);
    }

    protected function setUpInmemory()
    {
        config(['scribe.database_connections_to_transact' => [[
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]]]);
        Globals::$shouldBeVerbose = true;

        (new class extends Migration {
            function up() {
                Schema::create('test_users', function (Blueprint $table) {
                    $table->id();
                    $table->string('first_name')->nullable();
                    $table->string('last_name')->nullable();
                    $table->string('email')->nullable();
                    $table->foreignIdFor(InMemoryTestWork::class);
                    $table->foreignIdFor(InMemoryTestUser::class, 'parent_id')->nullable();
                    $table->timestamps();
                });
                Schema::create('test_pets', function (Blueprint $table) {
                    $table->id();
                    $table->string('name')->nullable();
                    $table->string('species')->nullable();
                    $table->timestamps();
                });
                Schema::create('test_pet_test_user', function (Blueprint $table) {
                    $table->foreignIdFor(InMemoryTestUser::class);
                    $table->foreignIdFor(InMemoryTestPet::class);
                    $table->integer('duration')->nullable();
                });
                Schema::create('test_works', function (Blueprint $table) {
                    $table->id();
                    $table->string('name')->nullable();
                    $table->timestamps();
                });
                Schema::create('test_departments', function (Blueprint $table) {
                    $table->id();
                    $table->string('name')->nullable();
                    $table->foreignIdFor(InMemoryTestWork::class);
                    $table->timestamps();
                });
            }
        })->up();
    }
}
