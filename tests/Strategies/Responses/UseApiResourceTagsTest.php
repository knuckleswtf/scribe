<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Database\Eloquent\LegacyFactoryServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tests\Fixtures\TestPet;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * @internal
 *
 * @coversNothing
 */
class UseApiResourceTagsTest extends BaseLaravelTest
{
    public function setUp(): void
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
    public function canParseApiresourceTags()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function canParseApiresourceTagsWithoutApiresourcemodel()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestEmptyApiResource'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));
        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [],
                    'request-id' => 'ea02ebc1-4e3c-497f-9ea8-7a1ac5008af2',
                    'error_code' => 0,
                    'messages' => [],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function respectsModelsSourceSettings()
    {
        $config = new DocumentationConfig(['examples' => ['models_source' => ['databaseFirst', 'factoryMake']]]);
        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];

        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
        });
        TestUser::create(['first_name' => 'Testy', 'last_name' => 'Testes', 'email' => 'um']);

        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 1,
                        'name' => 'Testy Testes',
                        'email' => 'um',
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseApiresourceTagsWithScenarioAndStatusAttributes()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', 'status=202 scenario="Success" \Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponseFromTags(
            $strategy->getApiResourceTag($tags),
            $tags,
            ExtractedEndpointData::fromRoute($route),
            false
        );

        $this->assertArraySubset([
            [
                'status' => 202,
                'description' => 'Success',
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
    public function properlyBindsRouteAndRequestWhenFetchingApiresourceResponse()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);
        $route->name('someone');

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function canParseApiresourcemodelTagsWithFactoryStates()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '201 \Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser states=state1,random-state'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function canInferModelFromMixinTagAndParseApiresourceTagsWithFactoryStates()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '201 \Knuckles\Scribe\Tests\Fixtures\TestUserApiResource states=state1,random-state'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function loadsSpecifiedRelationsForModel()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if (4 === $user->id) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));
            }
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function loadsSpecifiedRelationsForGeneratedModel()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if (4 === $user->id) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));
            }
        });
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=children'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function loadsSpecifiedNestedRelationsForGeneratedModel()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if (4 === $user->id) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));

                $grandchild = Utils::getModelFactory(TestUser::class)->make(['id' => 6, 'parent_id' => 5]);
                $child->setRelation('children', collect([$grandchild]));
            }
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=children.children'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loadsSpecifiedManyToManyRelationsForGeneratedModel()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            $pet = Utils::getModelFactory(TestPet::class)->make(['id' => 1]);
            $user->setRelation('pets', collect([$pet]));
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=pets'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loadsSpecifiedManyToManyAndNestedRelationsForGeneratedModel()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            if (4 === $user->id) {
                $child = Utils::getModelFactory(TestUser::class)->make(['id' => 5, 'parent_id' => 4]);
                $user->setRelation('children', collect([$child]));

                $pet = Utils::getModelFactory(TestPet::class)->make(['id' => 1]);
                $child->setRelation('pets', collect([$pet]));
            }
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=children.pets'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                                        'species' => 'dog',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loadsSpecifiedManyToManyRelationsForGeneratedModelWithPivot()
    {
        $factory = app(Factory::class);
        $factory->afterMaking(TestUser::class, function (TestUser $user, $faker) {
            $pet = Utils::getModelFactory(TestPet::class)->make(['id' => 1]);

            $pivot = $pet->newPivot($user, [
                'pet_id' => $pet->id,
                'user_id' => $user->id,
                'duration' => 2,
            ], 'pet_user', true);

            $pet->setRelation('pivot', $pivot);

            $user->setRelation('pets', collect([$pet]));
        });

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser with=pets'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                                    'duration' => 2,
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function loadsSpecifiedMorphToManyRelationsForGeneratedModelWithPivot()
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

        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestPostApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestPost with=tags'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    'data' => [
                        'id' => 1,
                        'title' => 'Test title',
                        'body' => 'random body',
                        'tags' => [
                            [
                                'id' => 1,
                                'name' => 'tag 1',
                                'priority' => 'high',
                            ],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseApiresourcecollectionTags()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function canParseApiresourcecollectionTagsWithCollectionClass()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResourceCollection'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function canParseApiresourcecollectionTagsWithCollectionClassAndPagination()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResourceCollection'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser paginate=1,simple'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                        'first' => '/?page=1',
                        'last' => null,
                        'prev' => null,
                        'next' => '/?page=2',
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'from' => 1,
                        'path' => '/',
                        'per_page' => '1',
                        'to' => 1,
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseApiresourceadditionalTags()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResource', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResource'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser'),
            new Tag('apiResourceAdditional', 'a=b "custom field"=c e="custom value" "another field"="true value"'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
    public function canParseApiresourcecollectionTagsWithCollectionClassPaginationAndApiresourceadditionalTag()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResourceCollection'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser paginate=1,simple'),
            new Tag('apiResourceAdditional', 'a=b'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                        'first' => '/?page=1',
                        'last' => null,
                        'prev' => null,
                        'next' => '/?page=2',
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'from' => 1,
                        'path' => '/',
                        'per_page' => '1',
                        'to' => 1,
                    ],
                    'a' => 'b',
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseApiresourcecollectionTagsWithCollectionClassAndCursorPagination()
    {
        $config = new DocumentationConfig([]);

        $route = new Route(['POST'], '/somethingRandom', ['uses' => [TestController::class, 'dummy']]);

        $strategy = new UseApiResourceTags($config);
        $tags = [
            new Tag('apiResourceCollection', '\Knuckles\Scribe\Tests\Fixtures\TestUserApiResourceCollection'),
            new Tag('apiResourceModel', '\Knuckles\Scribe\Tests\Fixtures\TestUser paginate=1,cursor'),
        ];
        $results = $strategy->getApiResourceResponseFromTags($strategy->getApiResourceTag($tags), $tags, ExtractedEndpointData::fromRoute($route));

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
                        ],
                    ],
                    'links' => [
                        'self' => 'link-value',
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

    protected function getPackageProviders($app)
    {
        $providers = parent::getPackageProviders($app);
        if (class_exists(LegacyFactoryServiceProvider::class)) {
            $providers[] = LegacyFactoryServiceProvider::class;
        }

        return $providers;
    }
}
