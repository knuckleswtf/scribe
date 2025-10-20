<?php

namespace Knuckles\Scribe\Tests\Strategies\QueryParameters;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

class GetFromQueryParamTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_queryparam_tag()
    {
        $strategy = new GetFromQueryParamTag(new DocumentationConfig([]));
        $tags = [
            new Tag('queryParam', 'location_id required The id of the location.'),
            new Tag('queryParam', 'user_id required The id of the user. Example: me'),
            new Tag('queryParam', 'page The page number. Example: 4'),
            new Tag('queryParam', 'with_type number Example: 13'),
            new Tag('queryParam', 'with_list_type int[]'),
            new Tag('queryParam', 'book_id string required deprecated Book ID'),
            new Tag('queryParam', 'team_id string deprecated Team ID'),
            new Tag('queryParam', 'teams array deprecated Teams Example: ["1", "2"]'),
            new Tag('queryParam', 'device_id deprecated Device ID'),
            new Tag('queryParam', 'device object deprecated Device'),
            new Tag('queryParam', 'devices array deprecated'),
            new Tag('queryParam', 'fields string[] The fields. Example: ["age", "name"]'),
            new Tag('queryParam', 'filters object The filters. '),
            new Tag('queryParam', 'filters.class double Class. Example: 11'),
            new Tag('queryParam', 'filters.other string required Other things.'),
            new Tag('queryParam', 'noExampleNoDescription No-example.'),
            new Tag('queryParam', 'noExample Something No-example'),
        ];
        $results = $strategy->getFromTags($tags);

        $this->assertArraySubset([
            'location_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'The id of the location.',
                'deprecated' => false,
            ],
            'user_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'The id of the user.',
                'example' => 'me',
            ],
            'page' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'The page number.',
                'example' => 4,
            ],
            'with_type' => [
                'type' => 'number',
                'required' => false,
                'description' => '',
                'example' => 13.0,
            ],
            'with_list_type' => [
                'type' => 'integer[]',
                'required' => false,
                'description' => '',
            ],
            'fields' => [
                'type' => 'string[]',
                'required' => false,
                'description' => 'The fields.',
                'example' => ['age', 'name']
            ],
            'filters' => [
                'type' => 'object',
                'required' => false,
                'description' => 'The filters.',
            ],
            'filters.class' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Class.',
                'example' => 11.0
            ],
            'filters.other' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Other things.',
            ],
            'noExampleNoDescription' => [
                'type' => 'string',
                'required' => false,
                'description' => '',
                'example' => null
            ],
            'noExample' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Something',
                'example' => null
            ],
            'book_id' => [
                'type' => 'string',
                'description' => 'Book ID',
                'required' => true,
                'deprecated' => true,
            ],
            'team_id' => [
                'type' => 'string',
                'description' => 'Team ID',
                'required' => false,
                'deprecated' => true,
            ],
            'device_id' => [
                'type' => 'string',
                'description' => 'Device ID',
                'required' => false,
                'deprecated' => true,
            ],
            'device' => [
                'type' => 'object',
                'description' => 'Device',
                'required' => false,
                'deprecated' => true,
            ],
            'devices' => [
                'type' => 'string[]',
                'description' => '',
                'required' => false,
                'deprecated' => true,
            ],
            'teams' => [
                'type' => 'string[]',
                'description' => 'Teams',
                'required' => false,
                'deprecated' => true,
                'example' => ['1', '2']
            ],
        ], $results);
    }

    /** @test */
    public function can_fetch_from_form_request_method_argument()
    {
        $methodName = 'withFormRequestParameter';
        $method = new \ReflectionMethod(TestController::class, $methodName);
        $route = new Route(['POST'], "/$methodName", ['uses' => [TestController::class, $methodName]]);

        $strategy = new GetFromQueryParamTag(new DocumentationConfig([]));
        $results = $strategy->getParametersFromDocBlockInFormRequestOrMethod($route, $method);

        $this->assertArraySubset([
            'location_id' => [
                'required' => true,
                'description' => 'The id of the location.',
            ],
            'user_id' => [
                'required' => true,
                'description' => 'The id of the user.',
                'example' => 'me',
            ],
            'page' => [
                'required' => true,
                'description' => 'The page number.',
                'example' => '4',
            ],
        ], $results);
    }

}
