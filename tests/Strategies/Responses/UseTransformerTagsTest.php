<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;

class UseTransformerTagsTest extends BaseLaravelTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig(['database_connections_to_transact' => []]);
    }

    /**
     * @param $serializer
     * @param $expected
     *
     * @test
     * @dataProvider serializerAndExpected
     */
    public function can_parse_transformer_tag($serializer, $expected)
    {
        $config = new DocumentationConfig(['fractal' => ['serializer' => $serializer]]);

        $strategy = new UseTransformerTags($config);
        $tags = [
            new Tag('transformer', '\Knuckles\Scribe\Tests\Fixtures\TestTransformer'),
        ];
        $results = $strategy->getTransformerResponseFromTags($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => $expected,
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_transformer_tag_with_model()
    {
        $strategy = new UseTransformerTags(new DocumentationConfig([]));
        $tags = [
            new Tag('transformer', '\Knuckles\Scribe\Tests\Fixtures\TestTransformer'),
            new Tag('transformermodel', '\Knuckles\Scribe\Tests\Fixtures\TestModel'),
        ];
        $results = $strategy->getTransformerResponseFromTags($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    "data" => [
                        "id" => 1,
                        "description" => "Welcome on this test versions",
                        "name" => "TestName",
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_transformer_tag_with_model_and_factory_states()
    {
        $factory = app(\Illuminate\Database\Eloquent\Factory::class);
        $factory->define(TestUser::class, function () {
            return ['id' => 3, 'name' => 'myname'];
        });
        $factory->state(TestUser::class, 'state1', ["state1" => true]);
        $factory->state(TestUser::class, 'random-state', ["random-state" => true]);

        $strategy = new UseTransformerTags(new DocumentationConfig([]));
        $tags = [
            new Tag('transformer', '\Knuckles\Scribe\Tests\Fixtures\TestEloquentTransformer'),
            new Tag('transformermodel', '\Knuckles\Scribe\Tests\Fixtures\TestUser states=state1,random-state'),
        ];
        $results = $strategy->getTransformerResponseFromTags($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    "data" => [
                        "id" => 3,
                        "name" => "myname",
                        "state1" => true,
                        "random-state" => true,
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_transformer_tag_with_status_code()
    {
        $strategy = new UseTransformerTags(new DocumentationConfig([]));
        $tags = [
            new Tag('transformer', '201 \Knuckles\Scribe\Tests\Fixtures\TestTransformer'),
        ];
        $results = $strategy->getTransformerResponseFromTags($tags);

        $this->assertArraySubset([
            [
                'status' => 201,
                'content' => json_encode([
                    "data" => [
                        "id" => 1,
                        "description" => "Welcome on this test versions",
                        "name" => "TestName",
                    ],
                ]),
            ],
        ], $results);

    }

    /** @test */
    public function can_parse_transformercollection_tag()
    {
        $strategy = new UseTransformerTags(new DocumentationConfig([]));
        $tags = [
            new Tag('transformercollection', '\Knuckles\Scribe\Tests\Fixtures\TestTransformer'),
        ];
        $results = $strategy->getTransformerResponseFromTags($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    "data" => [
                        [
                            "id" => 1,
                            "description" => "Welcome on this test versions",
                            "name" => "TestName",
                        ],
                        [
                            "id" => 1,
                            "description" => "Welcome on this test versions",
                            "name" => "TestName",
                        ],
                    ],
                ]),
            ],
        ], $results);

    }

    /** @test */
    public function can_parse_transformercollection_tag_with_model()
    {

        $strategy = new UseTransformerTags(new DocumentationConfig([]));
        $tags = [
            new Tag('transformercollection', '\Knuckles\Scribe\Tests\Fixtures\TestTransformer'),
            new Tag('transformermodel', '\Knuckles\Scribe\Tests\Fixtures\TestModel'),
        ];
        $results = $strategy->getTransformerResponseFromTags($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    "data" => [
                        [
                            "id" => 1,
                            "description" => "Welcome on this test versions",
                            "name" => "TestName",
                        ],
                        [
                            "id" => 1,
                            "description" => "Welcome on this test versions",
                            "name" => "TestName",
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function can_parse_transformercollection_tag_with_model_and_paginator_data()
    {

        $strategy = new UseTransformerTags(new DocumentationConfig([]));
        $tags = [
            new Tag('transformercollection', '\Knuckles\Scribe\Tests\Fixtures\TestTransformer'),
            new Tag('transformermodel', '\Knuckles\Scribe\Tests\Fixtures\TestModel'),
            new Tag('transformerpaginator', 'League\Fractal\Pagination\IlluminatePaginatorAdapter 1'),
        ];
        $results = $strategy->getTransformerResponseFromTags($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => json_encode([
                    "data" => [
                        [
                            "id" => 1,
                            "description" => "Welcome on this test versions",
                            "name" => "TestName",
                        ],
                    ],
                    'meta' => [
                        "pagination" => [
                            "total" => 2,
                            "count" => 1,
                            "per_page" => 1,
                            "current_page" => 1,
                            "total_pages" => 2,
                            "links" => ["next" => "/?page=2"],
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    public static function serializerAndExpected()
    {
        return [
            [
                null,
                json_encode([
                    "data" => [
                        "id" => 1,
                        "description" => "Welcome on this test versions",
                        "name" => "TestName",
                    ],
                ]),
            ],
            [
                'League\Fractal\Serializer\JsonApiSerializer',
                json_encode([
                    "data" => [
                        "type" => null,
                        "id" => "1",
                        "attributes" => [
                            "description" => "Welcome on this test versions",
                            "name" => "TestName",
                        ],
                    ],
                ]),
            ],
        ];
    }
}
