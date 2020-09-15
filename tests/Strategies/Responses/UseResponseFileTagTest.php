<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

class UseResponseFileTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @test
     * @dataProvider responseFileTags
     */
    public function allows_multiple_responsefile_tags_for_multiple_statuses_and_scenarios(array $tags, array $expected)
    {
        $filePath =  __DIR__ . '/../../Fixtures/response_test.json';
        $filePath2 =  __DIR__ . '/../../Fixtures/response_error_test.json';

        $strategy = new UseResponseFileTag(new DocumentationConfig([]));
        $results = $strategy->getFileResponses($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'description' => $expected[0]['description'],
                'content' => file_get_contents($filePath),
            ],
            [
                'status' => 401,
                'description' => $expected[1]['description'],
                'content' => file_get_contents($filePath2),
            ],
        ], $results);

    }

    /** @test */
    public function can_add_or_replace_key_value_pair_in_response_file()
    {
        $strategy = new UseResponseFileTag(new DocumentationConfig([]));
        $tags = [
            new Tag('responseFile', 'tests/Fixtures/response_test.json {"message" : "Serendipity", "gender": "male"}'),
        ];
        $results = $strategy->getFileResponses($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => '{"id":5,"name":"Jessica Jones","gender":"male","message":"Serendipity"}',
            ],
        ], $results);
    }

    public function responseFileTags()
    {
        return [
            "with status as initial position" => [
                [
                    new Tag('responseFile', 'tests/Fixtures/response_test.json'),
                    new Tag('responseFile', '401 tests/Fixtures/response_error_test.json'),
                ],
                [
                    [
                        'status' => 200,
                        'description' => '200',
                    ],
                    [
                        'status' => 401,
                        'description' => '401',
                    ],
                ],
            ],

            "with attributes" => [
                [
                    new Tag('responseFile', 'scenario="success" tests/Fixtures/response_test.json'),
                    new Tag('responseFile', 'status=401 scenario=\'auth problem\' tests/Fixtures/response_error_test.json'),
                ],
                [
                    [
                        'status' => 200,
                        'description' => '200, success',
                    ],
                    [
                        'status' => 401,
                        'description' => '401, auth problem',
                    ],
                ],
            ],
        ];
    }
}
