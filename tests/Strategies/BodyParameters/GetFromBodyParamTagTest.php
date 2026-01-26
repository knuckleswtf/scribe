<?php

namespace Knuckles\Scribe\Tests\Strategies\BodyParameters;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class GetFromBodyParamTagTest extends TestCase
{
    use ArraySubsetAsserts;

    protected GetFromBodyParamTag $strategy;

    protected function setUp(): void
    {
        $this->strategy = new GetFromBodyParamTag(new DocumentationConfig([]));
    }

    /** @test */
    public function canFetchFromBodyparamTag()
    {
        $tags = [
            new Tag('bodyParam', 'user_id int required The id of the user. Example: 9'),
            new Tag('bodyParam', 'room_id string The id of the room.'),
            new Tag('bodyParam', 'forever boolean Whether to ban the user forever. Example: false'),
            new Tag('bodyParam', 'another_one number Just need something here.'),
            new Tag('bodyParam', 'yet_another_param object required Some object params.'),
            new Tag('bodyParam', 'yet_another_param.name string required'),
            new Tag('bodyParam', 'even_more_param number[] A list of numbers'),
            new Tag('bodyParam', 'book_id string required deprecated Book ID'),
            new Tag('bodyParam', 'team_id string deprecated Team ID'),
            new Tag('bodyParam', 'teams array deprecated Teams Example: ["1", "2"]'),
            new Tag('bodyParam', 'device object deprecated Device'),
            new Tag('bodyParam', 'devices array deprecated'),
            new Tag('bodyParam', 'book object Book information'),
            new Tag('bodyParam', 'book.name string'),
            new Tag('bodyParam', 'book.author_id integer'),
            new Tag('bodyParam', 'book.pages_count integer'),
            new Tag('bodyParam', 'ids integer[]'),
            new Tag('bodyParam', 'users object[] Users\' details'),
            new Tag('bodyParam', 'users[].first_name string The first name of the user. Example: John'),
            new Tag('bodyParam', 'users[].last_name string The last name of the user. Example: Doe'),
        ];
        $results = $this->strategy->getFromTags($tags);

        $this->assertArraySubset([
            'user_id' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'The id of the user.',
                'example' => 9,
                'deprecated' => false,
            ],
            'room_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'The id of the room.',
            ],
            'forever' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Whether to ban the user forever.',
                'example' => false,
            ],
            'another_one' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Just need something here.',
            ],
            'yet_another_param' => [
                'type' => 'object',
                'required' => true,
                'description' => 'Some object params.',
            ],
            'yet_another_param.name' => [
                'type' => 'string',
                'description' => '',
                'required' => true,
            ],
            'even_more_param' => [
                'type' => 'number[]',
                'description' => 'A list of numbers',
                'required' => false,
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
                'example' => ['1', '2'],
            ],
            'book' => [
                'type' => 'object',
                'description' => 'Book information',
                'required' => false,
            ],
            'book.name' => [
                'type' => 'string',
                'description' => '',
                'required' => false,
            ],
            'book.author_id' => [
                'type' => 'integer',
                'description' => '',
                'required' => false,
            ],
            'book.pages_count' => [
                'type' => 'integer',
                'description' => '',
                'required' => false,
            ],
            'ids' => [
                'type' => 'integer[]',
                'description' => '',
                'required' => false,
            ],
            'users' => [
                'type' => 'object[]',
                'description' => 'Users\' details',
                'required' => false,
            ],
            'users[].first_name' => [
                'type' => 'string',
                'description' => 'The first name of the user.',
                'required' => false,
                'example' => 'John',
            ],
            'users[].last_name' => [
                'type' => 'string',
                'description' => 'The last name of the user.',
                'required' => false,
                'example' => 'Doe',
            ],
        ], $results);
    }

    /** @test */
    public function retainsNullAsExampleIfSpecified()
    {
        $tags = [
            new Tag('bodyParam', 'id int required The id to use. Leave null to autogenerate. Example: null'),
            new Tag('bodyParam', 'key string A key. Example: null'),
        ];
        $results = $this->strategy->getFromTags($tags);

        $this->assertArraySubset([
            'id' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'The id to use. Leave null to autogenerate.',
                'example' => null,
            ],
            'key' => [
                'type' => 'string',
                'required' => false,
                'description' => 'A key.',
                'example' => null,
            ],
        ], $results);
    }

    /** @test */
    public function canFetchFromBodyparamTagForArrayBody()
    {
        $tags = [
            new Tag('bodyParam', '[].first_name string The first name of the user. Example: John'),
            new Tag('bodyParam', '[].last_name string The last name of the user. Example: Doe'),
            new Tag('bodyParam', '[].contacts[].first_name string The first name of the contact. Example: John'),
            new Tag('bodyParam', '[].contacts[].last_name string The last name of the contact. Example: Doe'),
            new Tag('bodyParam', '[].roles string[] The name of the role. Example: ["Admin"]'),
        ];
        $results = $this->strategy->getFromTags($tags);

        $this->assertArraySubset([
            '[].first_name' => [
                'type' => 'string',
                'description' => 'The first name of the user.',
                'required' => false,
                'example' => 'John',
            ],
            '[].last_name' => [
                'type' => 'string',
                'description' => 'The last name of the user.',
                'required' => false,
                'example' => 'Doe',
            ],
            '[].contacts[].first_name' => [
                'type' => 'string',
                'description' => 'The first name of the contact.',
                'required' => false,
                'example' => 'John',
            ],
            '[].contacts[].last_name' => [
                'type' => 'string',
                'description' => 'The last name of the contact.',
                'required' => false,
                'example' => 'Doe',
            ],
            '[].roles' => [
                'type' => 'string[]',
                'description' => 'The name of the role.',
                'required' => false,
                'example' => ['Admin'],
            ],
        ], $results);
    }

    /** @test */
    public function canFetchFromFormRequestMethodArgument()
    {
        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameter');
        $route = new Route(['POST'], '/withFormRequestParameter', ['uses' => [TestController::class, 'withFormRequestParameter']]);

        $results = $this->strategy->getParametersFromDocBlockInFormRequestOrMethod($route, $method);

        $this->assertArraySubset([
            'user_id' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'The id of the user.',
                'example' => 9,
            ],
            'forever' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Whether to ban the user forever.',
                'example' => false,
            ],
            'yet_another_param' => [
                'type' => 'object',
                'required' => true,
                'description' => '',
            ],
            'even_more_param' => [
                'type' => 'string[]',
                'required' => false,
                'description' => '',
            ],
            'ids' => [
                'name' => 'ids',
                'type' => 'integer[]',
                'description' => '',
                'required' => false,
            ],
        ], $results);
    }

    /** @test */
    public function fetchesFromMethodWhenFormRequestIsNotAnnotated()
    {
        $methodName = 'withNonCommentedFormRequestParameter';
        $method = new \ReflectionMethod(TestController::class, $methodName);
        $route = new Route(['POST'], "/{$methodName}", ['uses' => [TestController::class, $methodName]]);

        $results = $this->strategy->getParametersFromDocBlockInFormRequestOrMethod($route, $method);

        $this->assertArraySubset([
            'direct_one' => [
                'type' => 'string',
                'description' => 'Is found directly on the method.',
            ],
        ], $results);
    }
}
