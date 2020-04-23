<?php

namespace Knuckles\Scribe\Tests\Unit;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Support\Arr;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Extracting\Generator;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Orchestra\Testbench\TestCase;

abstract class GeneratorTestCase extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @var \Knuckles\Scribe\Extracting\Generator
     */
    protected $generator;
    private $config = [
        'strategies' => [
            'metadata' => [
                \Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks::class,
            ],
            'urlParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamTag::class,
            ],
            'queryParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag::class,
            ],
            'headers' => [
                \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromRouteRules::class,
            ],
            'bodyParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag::class,
            ],
            'responses' => [
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls::class,
            ],
            'responseFields' => [
                \Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag::class,
            ],
        ],
        'default_group' => 'general',
    ];

    public static $globalValue = null;

    protected function getPackageProviders($app)
    {
        return [
            ScribeServiceProvider::class,
        ];
    }

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->generator = new Generator(new DocumentationConfig($this->config));
    }

    /** @test */
    public function clean_can_properly_parse_array_keys()
    {
        $parameters = [
            'object' => [
                'type' => 'object',
                'value' => [],
            ],
            'object_with_keys.key1' => [
                'type' => 'string',
                'value' => '43',
            ],
            'object_with_keys[key2]' => [
                'type' => 'integer',
                'value' => 77,
            ],
            'list' => [
                'type' => 'array',
                'value' => [],
            ],
            'list_with_types.*' => [
                'type' => 'integer',
                'value' => 4,
            ],
            'list_of_objects.*.key1' => [
                'type' => 'string',
                'value' => 'John',
            ],
            'list_of_objects.*.key2' => [
                'type' => 'boolean',
                'value' => false,
            ],
        ];

        $cleanBodyParameters = Generator::cleanParams($parameters);

        $this->assertEquals([
            'object' => [],
            'object_with_keys' => [
                'key1' => '43',
                'key2' => 77,
            ],
            'list' => [],
            'list_with_types' => [4],
            'list_of_objects' => [
                [
                    'key1' => 'John',
                    'key2' => false,
                ],
            ],
        ], $cleanBodyParameters);
    }

    /** @test */
    public function does_not_generate_values_for_excluded_params_and_excludes_them_from_clean_params()
    {
        $route = $this->createRoute('GET', '/api/test', 'withExcludedExamples');
        $parsed = $this->generator->processRoute($route);
        $cleanBodyParameters = $parsed['cleanBodyParameters'];
        $cleanQueryParameters = $parsed['cleanQueryParameters'];
        $bodyParameters = $parsed['bodyParameters'];
        $queryParameters = $parsed['queryParameters'];

        $this->assertArrayHasKey('included', $cleanBodyParameters);
        $this->assertArrayNotHasKey('excluded_body_param', $cleanBodyParameters);
        $this->assertEmpty($cleanQueryParameters);

        $this->assertArraySubset([
            'included' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Exists in examples.',
            ],
            'excluded_body_param' => [
                'type' => 'integer',
                'description' => 'Does not exist in examples.',
            ],
        ], $bodyParameters);

        $this->assertArraySubset([
            'excluded_query_param' => [
                'description' => 'Does not exist in examples.',
            ],
        ], $queryParameters);
    }

    /** @test */
    public function can_parse_route_methods()
    {
        $route = $this->createRoute('GET', '/get', 'withEndpointDescription');
        $parsed = $this->generator->processRoute($route);
        $this->assertEquals(['GET'], $parsed['methods']);

        $route = $this->createRoute('POST', '/post', 'withEndpointDescription');
        $parsed = $this->generator->processRoute($route);
        $this->assertEquals(['POST'], $parsed['methods']);

        $route = $this->createRoute('PUT', '/put', 'withEndpointDescription');
        $parsed = $this->generator->processRoute($route);
        $this->assertEquals(['PUT'], $parsed['methods']);

        $route = $this->createRoute('DELETE', '/delete', 'withEndpointDescription');
        $parsed = $this->generator->processRoute($route);
        $this->assertEquals(['DELETE'], $parsed['methods']);
    }

    /** @test */
    public function can_override_url_path_parameters_with_urlparam_annotation()
    {
        $route = $this->createRoute('POST', '/echoesUrlParameters/{param}', 'echoesUrlParameters', true);
        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];
        $parsed = $this->generator->processRoute($route, $rules);
        $response = json_decode(Arr::first($parsed['responses'])['content'], true);
        $this->assertEquals(4, $response['param']);
    }

    /** @test */
    public function ignores_or_inserts_optional_url_path_parameters_according_to_annotations()
    {
        $route = $this->createRoute('POST', '/echoesUrlParameters/{param}/{param2?}/{param3}/{param4?}', 'echoesUrlParameters', true);

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];
        $parsed = $this->generator->processRoute($route, $rules);
        $response = json_decode(Arr::first($parsed['responses'])['content'], true);
        $this->assertEquals(4, $response['param']);
        $this->assertNotNull($response['param2']);
        $this->assertEquals(1, $response['param3']);
        $this->assertNull($response['param4']);
    }

    /** @test */
    public function generates_consistent_examples_when_faker_seed_is_set()
    {
        $route = $this->createRoute('GET', '/withBodyParameters', 'withBodyParameters');

        $paramName = 'room_id';
        $results = [];
        $results[$this->generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        $results[$this->generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        $results[$this->generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        $results[$this->generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        $results[$this->generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        // Examples should have different values
        $this->assertNotEquals(count($results), 1);

        $generator = new Generator(new DocumentationConfig($this->config + ['faker_seed' => 12345]));
        $results = [];
        $results[$generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        $results[$generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        $results[$generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        $results[$generator->processRoute($route)['cleanBodyParameters'][$paramName]] = true;
        // Examples should have same values
        $this->assertEquals(count($results), 1);
    }

    /** @test */
    public function can_use_arrays_in_routes_uses()
    {
        $route = $this->createRouteUsesArray('GET', '/api/array/test', 'withEndpointDescription');

        $parsed = $this->generator->processRoute($route);

        $this->assertSame('Example title.', $parsed['metadata']['title']);
        $this->assertSame("This will be the long description.\nIt can also be multiple lines long.", $parsed['metadata']['description']);
    }

    /** @test */
    public function can_use_closure_in_routes_uses()
    {
        /**
         * A short title.
         * A longer description.
         * Can be multiple lines.
         *
         * @queryParam location_id required The id of the location.
         * @bodyParam name required Name of the location
         */
        $handler = function () {
            return 'hi';
        };
        $route = $this->createRouteUsesCallable('GET', '/api/closure/test', $handler);

        $parsed = $this->generator->processRoute($route);

        $this->assertSame('A short title.', $parsed['metadata']['title']);
        $this->assertSame("A longer description.\nCan be multiple lines.", $parsed['metadata']['description']);
        $this->assertCount(1, $parsed['queryParameters']);
        $this->assertCount(1, $parsed['bodyParameters']);
        $this->assertSame('The id of the location.', $parsed['queryParameters']['location_id']['description']);
        $this->assertSame('Name of the location', $parsed['bodyParameters']['name']['description']);
    }

    abstract public function createRoute(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class);

    abstract public function createRouteUsesArray(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class);

    abstract public function createRouteUsesCallable(string $httpMethod, string $path, callable $handler, $register = false);
}
