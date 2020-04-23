<?php

namespace Knuckles\Scribe\Tests\Extracting\Strategies\Responses;

use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

class UseResponseTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_response_tag()
    {
        $strategy = new UseResponseTag(new DocumentationConfig([]));
        $tags = [
            new Tag('response', '{
        "id": 4,
        "name": "banana",
        "color": "red",
        "weight": "1 kg",
        "delicious": true,
        "responseTag": true
      }'),
        ];
        $results = $strategy->getDocBlockResponses($tags);

        $this->assertEquals(200, $results[0]['status']);
        $this->assertArraySubset([
            'id' => 4,
            'name' => 'banana',
            'color' => 'red',
            'weight' => '1 kg',
            'delicious' => true,
        ], json_decode($results[0]['content'], true));
    }

    /** @test */
    public function allows_multiple_response_tags_for_multiple_statuses()
    {
        $strategy = new UseResponseTag(new DocumentationConfig([]));
        $tags = [
            new Tag('response', '{
       "id": 4,
       "name": "banana",
       "color": "red",
       "weight": "1 kg",
       "delicious": true,
       "multipleResponseTagsAndStatusCodes": true
     }'),
            new Tag('response', '401 {
       "message": "Unauthorized"
     }'),
        ];
        $results = $strategy->getDocBlockResponses($tags);

        $this->assertEquals(200, $results[0]['status']);
        $this->assertEquals(401, $results[1]['status']);
        $this->assertArraySubset([
            'id' => 4,
            'name' => 'banana',
            'color' => 'red',
            'weight' => '1 kg',
            'delicious' => true,
        ], json_decode($results[0]['content'], true));
        $this->assertArraySubset([
            'message' => 'Unauthorized',
        ], json_decode($results[1]['content'], true));
    }

}
