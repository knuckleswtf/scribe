<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\Parameter;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies;
use Knuckles\Scribe\Tests\BaseUnitTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;

class ExtractorTest extends BaseUnitTest
{
    protected Extractor $extractor;

    protected $config = [
        'strategies' => [
            'metadata' => [
                Strategies\Metadata\GetFromDocBlocks::class,
                \Knuckles\Scribe\Tests\Fixtures\TestCustomEndpointMetadata::class,
            ],
            'urlParameters' => [
                Strategies\UrlParameters\GetFromLaravelAPI::class,
                Strategies\UrlParameters\GetFromUrlParamTag::class,
            ],
            'queryParameters' => [
                Strategies\QueryParameters\GetFromQueryParamTag::class,
            ],
            'headers' => [
                Strategies\Headers\GetFromRouteRules::class,
                Strategies\Headers\GetFromHeaderTag::class,
            ],
            'bodyParameters' => [
                Strategies\BodyParameters\GetFromBodyParamTag::class,
            ],
            'responses' => [
                Strategies\Responses\UseResponseTag::class,
                Strategies\Responses\UseResponseFileTag::class,
            ],
            'responseFields' => [
                Strategies\ResponseFields\GetFromResponseFieldTag::class,
            ],
        ],
    ];

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->extractor = new Extractor(new DocumentationConfig($this->config));
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
                'example' => [],
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
                    'key1' => 'hoho',
                ],
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
        $route = $this->createRouteOldSyntax('POST', '/api/test', 'withExcludedExamples');
        $parsed = $this->extractor->processRoute($route)->toArray();
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
        $route = $this->createRouteOldSyntax('GET', '/get', 'withEndpointDescription');
        $parsed = $this->extractor->processRoute($route);
        $this->assertEquals(['GET'], $parsed->httpMethods);

        $route = $this->createRouteOldSyntax('POST', '/post', 'withEndpointDescription');
        $parsed = $this->extractor->processRoute($route);
        $this->assertEquals(['POST'], $parsed->httpMethods);

        $route = $this->createRouteOldSyntax('PUT', '/put', 'withEndpointDescription');
        $parsed = $this->extractor->processRoute($route);
        $this->assertEquals(['PUT'], $parsed->httpMethods);

        $route = $this->createRouteOldSyntax('DELETE', '/delete', 'withEndpointDescription');
        $parsed = $this->extractor->processRoute($route);
        $this->assertEquals(['DELETE'], $parsed->httpMethods);
    }

    /**
     * @test
     * @dataProvider authRules
     */
    public function adds_appropriate_field_based_on_configured_auth_type($config, $expected)
    {
        $route = $this->createRouteOldSyntax('POST', '/withAuthenticatedTag', 'withAuthenticatedTag');
        $generator = new Extractor(new DocumentationConfig(array_merge($this->config, $config)));
        $parsed = $generator->processRoute($route, [])->toArray();
        $this->assertNotNull($parsed[$expected['where']][$expected['name']]);
        $this->assertEquals($expected['where'], $parsed['auth'][0]);
        $this->assertEquals($expected['name'], $parsed['auth'][1]);
    }

    /** @test */
    public function generates_consistent_examples_when_faker_seed_is_set()
    {
        $route = $this->createRouteOldSyntax('POST', '/withBodyParameters', 'withBodyParameters');

        $paramName = 'room_id';
        $results = [];
        $results[$this->extractor->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$this->extractor->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$this->extractor->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$this->extractor->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$this->extractor->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        // Examples should have different values
        $this->assertNotEquals(1, count($results));

        $generator = new Extractor(new DocumentationConfig($this->config + ['examples' => ['faker_seed' => 12345]]));
        $results = [];
        $results[$generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        $results[$generator->processRoute($route)->cleanBodyParameters[$paramName]] = true;
        // Examples should have same values
        $this->assertEquals(1, count($results));
    }

    /** @test */
    public function can_use_arrays_in_routes_uses()
    {
        $route = $this->createRoute('GET', '/api/array/test', 'withEndpointDescription');

        $parsed = $this->extractor->processRoute($route);

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
        $handler = fn() => 'hi';
        $route = $this->createClosureRoute('POST', '/api/closure/test', $handler);

        $parsed = $this->extractor->processRoute($route);

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
        $route = $this->createRouteOldSyntax('POST', '/api/test', 'dummy');
        $parsed = $this->extractor->processRoute($route);
        $this->assertSame('some custom metadata', $parsed->metadata->custom['myProperty']);
    }

    /** @test */
    public function can_override_data_for_inherited_methods()
    {
        $route = $this->createRoute('POST', '/api/test', 'endpoint', TestParentController::class);
        $parent = $this->extractor->processRoute($route);
        $this->assertSame('Parent title', $parent->metadata->title);
        $this->assertSame('Parent group name', $parent->metadata->groupName);
        $this->assertSame('Parent description', $parent->metadata->description);
        $this->assertCount(1, $parent->responses);
        $this->assertCount(1, $parent->bodyParameters);
        $this->assertArraySubset(["type" => "integer"], $parent->bodyParameters['thing']->toArray());
        $this->assertEmpty($parent->queryParameters);

        $inheritedRoute = $this->createRoute('POST', '/api/test', 'endpoint', TestInheritedController::class);
        $inherited = $this->extractor->processRoute($inheritedRoute);
        $this->assertSame('Overridden title', $inherited->metadata->title);
        $this->assertSame('Overridden group name', $inherited->metadata->groupName);
        $this->assertSame('Parent description', $inherited->metadata->description);
        $this->assertCount(0, $inherited->responses);
        $this->assertCount(2, $inherited->bodyParameters);
        $this->assertArraySubset(["type" => "integer"], $inherited->bodyParameters['thing']->toArray());
        $this->assertArraySubset(["type" => "string"], $inherited->bodyParameters["other_thing"]->toArray());
        $this->assertCount(1, $inherited->queryParameters);
        $this->assertArraySubset(["type" => "string"], $inherited->queryParameters["queryThing"]->toArray());
    }

    public function createRoute(string $httpMethod, string $path, string $controllerMethod, $class = TestController::class)
    {
        return new Route([$httpMethod], $path, ['uses' => [$class, $controllerMethod]]);
    }

    public function createRouteOldSyntax(string $httpMethod, string $path, string $controllerMethod, $class = TestController::class)
    {
        return new Route([$httpMethod], $path, ['uses' => $class . "@$controllerMethod"]);
    }

    public function createClosureRoute(string $httpMethod, string $path, callable $handler)
    {
        return new Route([$httpMethod], $path, ['uses' => $handler]);
    }

    public static function authRules()
    {
        return [
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'bearer',
                        'name' => 'dfadb',
                    ],
                ],
                [
                    'name' => 'Authorization',
                    'where' => 'headers',
                ],
            ],
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'basic',
                        'name' => 'efwr',
                    ],
                ],
                [
                    'name' => 'Authorization',
                    'where' => 'headers',
                ],
            ],
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'header',
                        'name' => 'Api-Key',
                    ],
                ],
                [
                    'name' => 'Api-Key',
                    'where' => 'headers',
                ],
            ],
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'query',
                        'name' => 'apiKey',
                    ],
                ],
                [
                    'name' => 'apiKey',
                    'where' => 'queryParameters',
                ],
            ],
            [
                [
                    'auth' => [
                        'enabled' => true,
                        'in' => 'body',
                        'name' => 'access_token',
                    ],
                ],
                [
                    'name' => 'access_token',
                    'where' => 'bodyParameters',
                ],
            ],
        ];
    }
}


class TestParentController
{
    /**
     * Parent title
     *
     * Parent description
     *
     * @group Parent group name
     *
     * @bodyParam thing integer
     * @response {"hello":"there"}
     */
    public function endpoint()
    {

    }
}

class TestInheritedController extends TestParentController
{
    public static function inheritedDocsOverrides()
    {
        return [
            "endpoint" => [
                "metadata" => [
                    "title" => "Overridden title",
                    "groupName" => "Overridden group name",
                ],
                "queryParameters" => function (ExtractedEndpointData $endpointData) {
                    // Overrides
                    return [
                        'queryThing' => [
                            'type' => 'string',
                        ],
                    ];
                },
                "bodyParameters" => [
                    // Merges
                    "other_thing" => [
                        "type" => "string",
                    ],
                ],
                "responses" => function (ExtractedEndpointData $endpointData) {
                    // Completely overrides responses
                    return [];
                },
            ],
        ];
    }
}
