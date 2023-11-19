<?php

namespace Knuckles\Scribe\Tests\Strategies\BodyParameters;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

class GetFromBodyParamTagTest extends TestCase
{
    use ArraySubsetAsserts;

    protected GetFromBodyParamTag $strategy;

    protected function setUp(): void
    {
        $this->strategy = new GetFromBodyParamTag(new DocumentationConfig([]));
    }

    /** @test */
    public function can_fetch_from_bodyparam_tag()
    {
        $tags = [
            new Tag('bodyParam', 'user_id int required The id of the user. Example: 9'),
            new Tag('bodyParam', 'room_id string The id of the room.'),
            new Tag('bodyParam', 'forever boolean Whether to ban the user forever. Example: false'),
            new Tag('bodyParam', 'another_one number Just need something here.'),
            new Tag('bodyParam', 'yet_another_param object required Some object params.'),
            new Tag('bodyParam', 'yet_another_param.name string required'),
            new Tag('bodyParam', 'even_more_param number[] A list of numbers'),
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
    public function retains_null_as_example_if_specified()
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
    public function can_fetch_from_bodyparam_tag_for_array_body()
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
    public function can_fetch_from_form_request_method_argument()
    {
        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameter');
        $route = new Route(['POST'], "/withFormRequestParameter", ['uses' => [TestController::class, 'withFormRequestParameter']]);

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
            "ids" => [
                "name" => "ids",
                "type" => "integer[]",
                "description" => "",
                "required" => false,
            ],
        ], $results);
    }

    /** @test */
    public function fetches_from_method_when_form_request_is_not_annotated()
    {
        $methodName = 'withNonCommentedFormRequestParameter';
        $method = new \ReflectionMethod(TestController::class, $methodName);
        $route = new Route(['POST'], "/$methodName", ['uses' => [TestController::class, $methodName]]);

        $results = $this->strategy->getParametersFromDocBlockInFormRequestOrMethod($route, $method);

        $this->assertArraySubset([
            'direct_one' => [
                'type' => 'string',
                'description' => 'Is found directly on the method.',
            ],
        ], $results);
    }

}
