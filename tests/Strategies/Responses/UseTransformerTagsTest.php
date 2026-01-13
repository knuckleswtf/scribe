<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Illuminate\Database\Eloquent\Factory;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestUser;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * @internal
 *
 * @coversNothing
 */
class UseTransformerTagsTest extends BaseLaravelTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig(['database_connections_to_transact' => []]);
    }

    /**
     * @test
     *
     * @dataProvider serializerAndExpected
     *
     * @param mixed $serializer
     * @param mixed $expected
     */
    public function canParseTransformerTag($serializer, $expected)
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

    public static function serializerAndExpected()
    {
        return [
            [
                null,
                json_encode([
                    'data' => [
                        'id' => 1,
                        'description' => 'Welcome on this test versions',
                        'name' => 'TestName',
                    ],
                ]),
            ],
            [
                'League\Fractal\Serializer\JsonApiSerializer',
                json_encode([
                    'data' => [
                        'type' => null,
                        'id' => '1',
                        'attributes' => [
                            'description' => 'Welcome on this test versions',
                            'name' => 'TestName',
                        ],
                    ],
                ]),
            ],
        ];
    }

    /** @test */
    public function canParseTransformerTagWithModel()
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
                    'data' => [
                        'id' => 1,
                        'description' => 'Welcome on this test versions',
                        'name' => 'TestName',
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseTransformerTagWithModelAndFactoryStates()
    {
        $factory = app(Factory::class);
        $factory->define(TestUser::class, function () {
            return ['id' => 3, 'name' => 'myname'];
        });
        $factory->state(TestUser::class, 'state1', ['state1' => true]);
        $factory->state(TestUser::class, 'random-state', ['random-state' => true]);

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
                    'data' => [
                        'id' => 3,
                        'name' => 'myname',
                        'state1' => true,
                        'random-state' => true,
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseTransformerTagWithStatusCode()
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
                    'data' => [
                        'id' => 1,
                        'description' => 'Welcome on this test versions',
                        'name' => 'TestName',
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseTransformercollectionTag()
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
                    'data' => [
                        [
                            'id' => 1,
                            'description' => 'Welcome on this test versions',
                            'name' => 'TestName',
                        ],
                        [
                            'id' => 1,
                            'description' => 'Welcome on this test versions',
                            'name' => 'TestName',
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseTransformercollectionTagWithModel()
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
                    'data' => [
                        [
                            'id' => 1,
                            'description' => 'Welcome on this test versions',
                            'name' => 'TestName',
                        ],
                        [
                            'id' => 1,
                            'description' => 'Welcome on this test versions',
                            'name' => 'TestName',
                        ],
                    ],
                ]),
            ],
        ], $results);
    }

    /** @test */
    public function canParseTransformercollectionTagWithModelAndPaginatorData()
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
}
