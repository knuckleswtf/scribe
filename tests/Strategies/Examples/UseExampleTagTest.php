<?php

namespace Knuckles\Scribe\Tests\Strategies\Examples;

use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Knuckles\Scribe\Exceptions\ExampleResponseStatusCodeNotFound;
use Knuckles\Scribe\Exceptions\ExampleTypeNotFound;
use Knuckles\Scribe\Extracting\Strategies\Examples\UseExampleTag;
use PHPUnit\Framework\TestCase;

class UseExampleTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @test
     * @dataProvider exampleResponseTags
     */
    public function allows_multiple_example_tags_with_type_response_for_multiple_statuses_and_scenarios(array $tags, array $expected)
    {
        $strategy = new UseExampleTag(new DocumentationConfig([]));
        $results = $strategy->getDocBlockExamples($tags);

        $this->assertEquals($expected[0]['status'], $results[0]['meta']['status']);
        $this->assertEquals($expected[1]['status'], $results[1]['meta']['status']);
        $this->assertEquals($expected[0]['description'], $results[0]['description']);
        $this->assertEquals($expected[1]['description'], $results[1]['description']);
        $this->assertEquals($expected[0]['content'], json_decode($results[0]['content'], true));
        $this->assertEquals($expected[1]['content'], json_decode($results[1]['content'], true));
    }

    /**
     * @test
     * @dataProvider exampleRequestTags
     */
    public function allows_multiple_example_tags_with_type_request_for_multiple_scenarios(array $tags, array $expected)
    {
        $strategy = new UseExampleTag(new DocumentationConfig([]));
        $results = $strategy->getDocBlockExamples($tags);

        $this->assertEquals('request', $results[0]['type']);
        $this->assertEquals('request', $results[1]['type']);
        $this->assertEquals($expected[0]['description'], $results[0]['description']);
        $this->assertEquals($expected[1]['description'], $results[1]['description']);
        $this->assertEquals($expected[0]['content'], json_decode($results[0]['content'], true));
        $this->assertEquals($expected[1]['content'], json_decode($results[1]['content'], true));
    }

    /**
     * @test
     * @dataProvider exampleFileTags
     */
    public function allows_multiple_examples_with_files_for_multiple_statuses_and_scenarios(array $tags, array $expected)
    {
        $filePath = __DIR__ . '/../../Fixtures/response_test.json';
        $filePath2 = __DIR__ . '/../../Fixtures/response_error_test.json';

        $strategy = new UseExampleTag(new DocumentationConfig([]));
        $results = $strategy->getDocBlockExamples($tags);

        $this->assertArraySubset([
            [
                'type' => 'request',
                'meta' => [],
                'description' => $expected[0]['description'],
                'content' => file_get_contents($filePath),
            ],
            [
                'type' => 'response',
                'meta' => [
                    'status' => 401,
                ],
                'description' => $expected[1]['description'],
                'content' => file_get_contents($filePath2),
            ],
        ], $results);
    }

    /**
     * @test
     * @dataProvider invalidExampleTags
     */
    public function will_throw_an_exception_when_missing_or_invalid_type_attribue(array $tags)
    {
        $this->expectException(ExampleTypeNotFound::class);

        $strategy = new UseExampleTag(new DocumentationConfig([]));
        $strategy->getDocBlockExamples($tags);
    }

    /**
     * @test
     * @dataProvider invalidExampleResponseTags
     */
    public function will_throw_an_exception_when_missing_reponse_status_code_attribue(array $tags)
    {
        $this->expectException(ExampleResponseStatusCodeNotFound::class);

        $strategy = new UseExampleTag(new DocumentationConfig([]));
        $strategy->getDocBlockExamples($tags);
    }

    public static function exampleResponseTags()
    {
        $response1 = '{
       "id": 4,
       "name": "banana"
     }';
        $response2 = '{
        "message": "Unauthorized"
     }';
        return [
            "with fields" => [
                [
                    new Tag('example', "type=response status=200 scenario=\"success\" $response1"),
                    new Tag('example', "type=response status=401 scenario='auth problem' $response2"),
                ],
                [
                    [
                        'status' => 200,
                        'description' => 'success',
                        'content' => [
                            'id' => 4,
                            'name' => 'banana',
                        ],
                    ],
                    [
                        'status' => 401,
                        'description' => 'auth problem',
                        'content' => [
                            'message' => 'Unauthorized',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function exampleRequestTags()
    {
        $request1 = '{
       "id": 4,
       "name": "banana"
     }';
        $request2 = '{
        "message": "Unauthorized"
     }';
        return [
            "with fields" => [
                [
                    new Tag('example', "type=request scenario=\"with customer filters\" $request1"),
                    new Tag('example', "type=request scenario='with company filters' $request2"),
                ],
                [
                    [
                        'description' => 'with customer filters',
                        'content' => [
                            'id' => 4,
                            'name' => 'banana',
                        ],
                    ],
                    [
                        'description' => 'with company filters',
                        'content' => [
                            'message' => 'Unauthorized',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function exampleFileTags()
    {
        return [
            "with fields" => [
                [
                    new Tag('example', 'type="request" scenario="user update" file="tests/Fixtures/response_test.json"'),
                    new Tag('example', 'type="response" status=401 scenario=\'auth problem\' file="tests/Fixtures/response_error_test.json"'),
                ],
                [
                    [
                        'description' => 'user update',
                    ],
                    [
                        'status' => 401,
                        'description' => 'auth problem',
                    ],
                ],
            ],
        ];
    }

    public static function invalidExampleTags()
    {
        return [
            "with fields" => [
                [
                    new Tag('example', "scenario=\"with customer filters\""),
                    new Tag('example', "type=invalid scenario='with company filters'"),
                ],
            ],
        ];
    }
    
    public static function invalidExampleResponseTags()
    {
        return [
            "with fields" => [
                [
                    new Tag('example', "type=response"),
                    new Tag('example', "type=response scenario='Successful response'"),
                ],
            ],
        ];
    }
}
