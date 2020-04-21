<?php

namespace Knuckles\Scribe\Tests\Strategies\ResponseFields;

use Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class GetFromResponseFieldTagTest extends TestCase
{
    use ArraySubsetAsserts;

    public function testCanFetchFromResponseFieldTag()
    {
        $strategy = new GetFromResponseFieldTag(new DocumentationConfig([]));
        $tags = [
            new Tag('responseField', 'id int The id of the newly created user.'),
        ];
        $results = $strategy->getResponseFieldsFromDocBlock($tags, []);

        $this->assertArraySubset([
            'id' => [
                'type' => 'integer',
                'description' => 'The id of the newly created user.',
            ],
        ], $results);
    }

    public function testCanInferTypeFromFirst2xxResponse()
    {
        $strategy = new GetFromResponseFieldTag(new DocumentationConfig([]));
        $tags = [
            new Tag('responseField', 'id The id of the newly created user.'),
        ];
        $responses = [
            [
                'status' => 400,
                'content' => json_encode(['id' => 6.4]),
            ],
            [
                'status' => 200,
                'content' => json_encode(['id' => 6]),
            ],
            [
                'status' => 201,
                'content' => json_encode(['id' => 'haha']),
            ],
        ];
        $results = $strategy->getResponseFieldsFromDocBlock($tags, $responses);

        $this->assertArraySubset([
            'id' => [
                'type' => 'integer',
                'description' => 'The id of the newly created user.',
            ],
        ], $results);
    }

    public function testCanInferTypeFromFirst2xxResponseForLists()
    {
        $strategy = new GetFromResponseFieldTag(new DocumentationConfig([]));
        $tags = [
            new Tag('responseField', 'id The id of the newly created user.'),
        ];
        $responses = [
            [
                'status' => 200,
                'content' => json_encode([['id' => 6]]),
            ],
        ];
        $results = $strategy->getResponseFieldsFromDocBlock($tags, $responses);

        $this->assertArraySubset([
            'id' => [
                'type' => 'integer',
                'description' => 'The id of the newly created user.',
            ],
        ], $results);
    }

    public function testDefaultsToNothingWhenTypeInferenceFails()
    {
        $strategy = new GetFromResponseFieldTag(new DocumentationConfig([]));
        $tags = [
            new Tag('responseField', 'id The id of the newly created user.'),
        ];
        $results = $strategy->getResponseFieldsFromDocBlock($tags, []);

        $this->assertArraySubset([
            'id' => [
                'type' => '',
                'description' => 'The id of the newly created user.',
            ],
        ], $results);
    }

}
