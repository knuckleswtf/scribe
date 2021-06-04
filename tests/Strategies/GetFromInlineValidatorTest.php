<?php

namespace Knuckles\Scribe\Tests\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class GetFromInlineValidatorTest extends BaseLaravelTest
{
    use ArraySubsetAsserts;

    private static $expected = [
        'user_id' => [
            'type' => 'integer',
            'required' => true,
            'description' => 'The id of the user.',
            'example' => 9,
        ],
        'room_id' => [
            'type' => 'string',
            'required' => false,
            'description' => 'The id of the room. Must be one of <code>3</code>, <code>5</code>, or <code>6</code>.',
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
        'even_more_param' => [
            'type' => 'string[]',
            'required' => false,
            'description' => '',
        ],
        'book' => [
            'type' => 'object',
            'description' => '',
            'required' => false,
            'example' => [],
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
            'description' => '',
            'required' => false,
            'example' => [[]],
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
    ];

    /** @test */
    public function can_fetch_from_request_validate()
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->method = new \ReflectionMethod(TestController::class, 'withInlineRequestValidate');
            }
        };

        $strategy = new BodyParameters\GetFromInlineValidator(new DocumentationConfig([]));
        $results = $strategy($endpoint, []);

        $this->assertArraySubset(self::$expected, $results);
        $this->assertIsArray($results['ids']['example']);
    }

    /** @test */
    public function can_fetch_from_validator_make()
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->method = new \ReflectionMethod(TestController::class, 'withInlineValidatorMake');
            }
        };

        $strategy = new BodyParameters\GetFromInlineValidator(new DocumentationConfig([]));
        $results = $strategy($endpoint, []);

        $this->assertArraySubset(self::$expected, $results);
        $this->assertIsArray($results['ids']['example']);
    }

    /** @test */
    public function respects_query_params_comment()
    {
        $queryParamsEndpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->method = new \ReflectionMethod(TestController::class, 'withInlineRequestValidateQueryParams');
            }
        };

        $strategy = new BodyParameters\GetFromInlineValidator(new DocumentationConfig([]));
        $results = $strategy($queryParamsEndpoint, []);
        $this->assertEquals([], $results);

        $queryParamsStrategy = new QueryParameters\GetFromInlineValidator(new DocumentationConfig([]));
        $results = $queryParamsStrategy($queryParamsEndpoint, []);
        $this->assertArraySubset(self::$expected, $results);
        $this->assertIsArray($results['ids']['example']);

        $bodyParamsEndpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
                $this->method = new \ReflectionMethod(TestController::class, 'withInlineRequestValidate');
            }
        };
        $results = $queryParamsStrategy($bodyParamsEndpoint, []);
        $this->assertEquals([], $results);
    }

}
