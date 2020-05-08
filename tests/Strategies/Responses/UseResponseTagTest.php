<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

class UseResponseTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @test
     * @dataProvider responseTags
     */
    public function allows_multiple_response_tags_for_multiple_statuses_and_scenarios(array $tags, array $expected)
    {
        $strategy = new UseResponseTag(new DocumentationConfig([]));
        $results = $strategy->getDocBlockResponses($tags);

        $this->assertEquals($expected[0]['status'], $results[0]['status']);
        $this->assertEquals($expected[1]['status'], $results[1]['status']);
        $this->assertEquals($expected[0]['description'], $results[0]['description']);
        $this->assertEquals($expected[1]['description'], $results[1]['description']);
        $this->assertEquals($expected[0]['content'], json_decode($results[0]['content'], true));
        $this->assertEquals($expected[1]['content'], json_decode($results[1]['content'], true));
    }

    public function responseTags()
    {
        $response1 = '{
       "id": 4,
       "name": "banana"
     }';
        $response2 = '{
        "message": "Unauthorized"
     }';
        return [
            "with status as initial position" => [
                [
                    new Tag('response', $response1),
                    new Tag('response', "401 $response2"),
                ],
                [
                    [
                        'status' => 200,
                        'description' => '200',
                        'content' => [
                            'id' => 4,
                            'name' => 'banana',
                        ],
                    ],
                    [
                        'status' => 401,
                        'description' => '401',
                        'content' => [
                            'message' => 'Unauthorized',
                        ],
                    ],
                ],
            ],

            "with attributes" => [
                [
                    new Tag('response', "scenario=\"success\" $response1"),
                    new Tag('response', "status=401 scenario='auth problem' $response2"),
                ],
                [
                    [
                        'status' => 200,
                        'description' => '200, success',
                        'content' => [
                            'id' => 4,
                            'name' => 'banana',
                        ],
                    ],
                    [
                        'status' => 401,
                        'description' => '401, auth problem',
                        'content' => [
                            'message' => 'Unauthorized',
                        ],
                    ],
                ],
            ],
        ];
    }
}
