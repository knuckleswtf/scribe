<?php

namespace Knuckles\Scribe\Tests\Unit;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\Parameter;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @var \Knuckles\Scribe\Extracting\Extractor
     */
    protected $generator;

    protected $config = [
        'strategies' => [
            'metadata' => [
                \Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks::class,
                \Knuckles\Scribe\Tests\Fixtures\TestCustomEndpointMetadata::class,
            ],
            'urlParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI::class,
                \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamTag::class,
            ],
            'queryParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag::class,
            ],
            'headers' => [
                \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromRouteRules::class,
                \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderTag::class,
            ],
            'bodyParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag::class,
            ],
            'responses' => [
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls::class,
            ],
            'responseFields' => [
                \Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag::class,
            ],
        ],
        'default_group' => 'general',
    ];

    public static $globalValue = null;

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->generator = new Extractor(new DocumentationConfig($this->config));
    }

    /** @test */
    public function clean_can_properly_parse_array_keys()
    {
        $parameters = Parameter::arrayOf([
            'object' => [
                'name' => 'object',
                'type' => 'object',
                'example' => [],
            ],
            'object.key1' => [
                'name' => 'object.key1',
                'type' => 'string',
                'example' => '43',
            ],
            'object.key2' => [
                'name' => 'object.key2',
                'type' => 'integer',
                'example' => 77,
            ],
            'object.key3' => [
                'name' => 'object.key3',
                'type' => 'object',
                'example'=> [],
            ],
            'object.key3.key1' => [
                'name' => 'object.key3.key1',
                'type' => 'string',
                'example' => 'hoho',
            ],
            'list' => [
                'name' => 'list',
                'type' => 'integer[]',
                'example' => [4],
            ],
            'list_of_objects' => [
                'name' => 'list_of_objects',
                'type' => 'object[]',
                'example' => [[]],
            ],
            'list_of_objects[].key1' => [
                'name' => 'list_of_objects.key1',
                'type' => 'string',
                'required' => true,
                'example' => 'John',
            ],
            'list_of_objects[].key2' => [
                'name' => 'list_of_objects.key2',
                'type' => 'boolean',
                'required' => true,
                'example' => false,
            ],
        ]);

        $cleanBodyParameters = Extractor::cleanParams($parameters);

        $this->assertEquals([
            'object' => [
                'key1' => '43',
                'key2' => 77,
                'key3' => [
                    'key1' => 'hoho'
                ]
            ],
            'list' => [4],
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
        $route = $this->createRoute('POST', '/api/test', 'withExcludedExamples');
        $parsed = $this->generator->processRoute($route)->toArray();
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
        $this->assertEquals(['GET'], $parsed->httpMethods);

        $route = $this->createRoute('POST', '/post', 'withEndpointDescription');
        $parsed = $this->generator->processRoute($route);
        $this->assertEquals(['POST'], $parsed->httpMethods);

        $route = $this->createRoute('PUT', '/put', 'withEndpointDescription');
        $parsed = $this->generator->processRoute($route);
        $this->assertEquals(['PUT'], $parsed->httpMethods);

        $route = $this->createRoute('DELETE', '/delete', 'withEndpointDescription');
        $parsed = $this->generator->processRoute($route);
        $this->assertEquals(['DELETE'], $parsed->httpMethods);
    }

    /**
     * @test
     * @dataProvider authRules
     */
    public function adds_appropriate_field_based_on_configured_auth_type($config, $expected)
    {
        $route = $this->createRoute('POST', '/withAuthenticatedTag', 'withAuthenticatedTag', true);
        $generator = new Extractor(new DocumentationConfig(array_merge($this->config, $config)));
        $parsed = $generator->processRoute($route, [])->toArray();
        $this->assertNotNull($parsed[$expected['where']][$expected['name']]);
        $this->assertEquals($expected['where'], $parsed['auth'][0]);
        $this->assertEquals($expected['name'], $parsed['auth'][1]);
    }

    /** @test */
    public function generates_consistent_examples_when_faker_seed_is_set()
    {
        $route = $this->createRoute('POST', '/withBodyParameters', 'withBodyParameters');

        $paramName = 'room_id';
        $results = [];
        $results[$this->generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$this->generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$this->generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$this->generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$this->generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        // Examples should have different values
        $this->assertNotEquals(count($results), 1);

        $generator = new Extractor(new DocumentationConfig($this->config + ['faker_seed' => 12345]));
        $results = [];
        $results[$generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        // Examples should have same values
        $this->assertEquals(count($results), 1);
    }

    /** @test */
    public function can_use_arrays_in_routes_uses()
    {
        $route = $this->createRouteUsesArray('GET', '/api/array/test', 'withEndpointDescription');

        $parsed = $this->generator->processRoute($route);

        $this->assertSame('Example title.', $parsed->metadata->title);
        $this->assertSame("This will be the long description.\nIt can also be multiple lines long.", $parsed->metadata->description);
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
        $route = $this->createRouteUsesCallable('POST', '/api/closure/test', $handler);

        $parsed = $this->generator->processRoute($route);

        $this->assertSame('A short title.', $parsed->metadata->title);
        $this->assertSame("A longer description.\nCan be multiple lines.", $parsed->metadata->description);
        $this->assertCount(1, $parsed->queryParameters);
        $this->assertCount(1, $parsed->bodyParameters);
        $this->assertSame('The id of the location.', $parsed->queryParameters['location_id']->description);
        $this->assertSame('Name of the location', $parsed->bodyParameters['name']->description);
    }

    /** @test */
    public function endpoint_metadata_supports_custom_declarations()
    {
        $route = $this->createRoute('POST', '/api/test', 'dummy');
        $parsed = $this->generator->processRoute($route);
        $this->assertSame('some custom metadata', $parsed->metadata->custom['myProperty']);
    }

    public function createRoute(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        return new Route([$httpMethod], $path, ['uses' => $class . "@$controllerMethod"]);
    }

    public function createRouteUsesArray(string $httpMethod, string $path, string $controllerMethod, $register = false, $class = TestController::class)
    {
        return new Route([$httpMethod], $path, ['uses' => [$class, $controllerMethod]]);
    }

    public function createRouteUsesCallable(string $httpMethod, string $path, callable $handler, $register = false)
    {
        return new Route([$httpMethod], $path, ['uses' => $handler]);
    }

    public function authRules()
    {
        return [
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'bearer',
                        'name' => 'dfadb',
                    ]
                ],
                [
                    'name' => 'Authorization',
                    'where' => 'headers',
                ]
            ],
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'basic',
                        'name' => 'efwr',
                    ]
                ],
                [
                    'name' => 'Authorization',
                    'where' => 'headers',
                ]
            ],
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'header',
                        'name' => 'Api-Key',
                    ]
                ],
                [
                    'name' => 'Api-Key',
                    'where' => 'headers',
                ]
            ],
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'query',
                        'name' => 'apiKey',
                    ]
                ],
                [
                    'name' => 'apiKey',
                    'where' => 'queryParameters',
                ]
            ],
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'body',
                        'name' => 'access_token',
                    ]
                ],
                [
                    'name' => 'access_token',
                    'where' => 'bodyParameters',
                ]
            ],
        ];
    }
}
