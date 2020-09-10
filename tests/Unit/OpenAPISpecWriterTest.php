<?php

namespace Knuckles\Scribe\Tests\Unit;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Faker\Factory;
use Knuckles\Scribe\Extracting\Generator;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\OpenAPISpecWriter;
use PHPUnit\Framework\TestCase;

/**
 * See https://swagger.io/specification/
 */
class OpenAPISpecWriterTest extends TestCase
{
    use ArraySubsetAsserts;

    protected $config = [
        'title' => 'My Testy Testes API',
        'description' => 'All about testy testes.',
        'base_url' => 'http://api.api.dev',
    ];

    /** @test */
    public function follows_correct_spec_structure()
    {
        $fakeRoute1 = $this->createMockRouteData();
        $fakeRoute2 = $this->createMockRouteData();
        $groupedEndpoints = collect([$fakeRoute1, $fakeRoute2])->groupBy('metadata.groupName');

        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertEquals(OpenAPISpecWriter::VERSION, $results['openapi']);
        $this->assertEquals($this->config['title'], $results['info']['title']);
        $this->assertEquals($this->config['description'], $results['info']['description']);
        $this->assertNotEmpty($results['info']['version']);
        $this->assertEquals($this->config['base_url'], $results['servers'][0]['url']);
        $this->assertIsArray($results['paths']);
        $this->assertGreaterThan(0, count($results['paths']));
    }

    /** @test */
    public function adds_endpoints_correctly_as_operations_under_paths()
    {
        $fakeRoute1 = $this->createMockRouteData(['uri' => 'path1', 'methods' => ['GET']]);
        $fakeRoute2 = $this->createMockRouteData(['uri' => 'path1', 'methods' => ['POST']]);
        $fakeRoute3 = $this->createMockRouteData(['uri' => 'path1/path2']);
        $groupedEndpoints = collect([$fakeRoute1, $fakeRoute2, $fakeRoute3])->groupBy('metadata.groupName');

        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertIsArray($results['paths']);
        $this->assertCount(2, $results['paths']);
        $this->assertCount(2, $results['paths']['/path1']);
        $this->assertCount(1, $results['paths']['/path1/path2']);
        $this->assertArrayHasKey('get', $results['paths']['/path1']);
        $this->assertArrayHasKey('post', $results['paths']['/path1']);
        $this->assertArrayHasKey(strtolower($fakeRoute3['methods'][0]), $results['paths']['/path1/path2']);

        collect([$fakeRoute1, $fakeRoute2, $fakeRoute3])->each(function ($endpoint) use ($results) {
            $method = strtolower($endpoint['methods'][0]);
            $this->assertEquals([$endpoint['metadata']['groupName']], $results['paths']['/' . $endpoint['uri']][$method]['tags']);
            $this->assertEquals($endpoint['metadata']['title'], $results['paths']['/' . $endpoint['uri']][$method]['summary']);
            $this->assertEquals($endpoint['metadata']['description'], $results['paths']['/' . $endpoint['uri']][$method]['description']);
        });
    }

    /** @test */
    public function adds_authentication_details_correctly_as_security_info()
    {
        $fakeRoute1 = $this->createMockRouteData(['uri' => 'path1', 'methods' => ['GET'], 'metadata.authenticated' => true]);
        $fakeRoute2 = $this->createMockRouteData(['uri' => 'path1', 'methods' => ['POST'], 'metadata.authenticated' => false]);
        $groupedEndpoints = collect([$fakeRoute1, $fakeRoute2])->groupBy('metadata.groupName');

        $config = array_merge($this->config, ['auth' => ['enabled' => true, 'in' => 'bearer']]);
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertCount(1, $results['components']['securitySchemes']);
        $this->assertArrayHasKey('default', $results['components']['securitySchemes']);
        $this->assertEquals('http', $results['components']['securitySchemes']['default']['type']);
        $this->assertEquals('bearer', $results['components']['securitySchemes']['default']['scheme']);
        $this->assertCount(1, $results['security']);
        $this->assertCount(1, $results['security'][0]);
        $this->assertArrayHasKey('default', $results['security'][0]);
        $this->assertArrayNotHasKey('security', $results['paths']['/path1']['get']);
        $this->assertArrayHasKey('security', $results['paths']['/path1']['post']);
        $this->assertCount(0, $results['paths']['/path1']['post']['security']);

        // Next try: auth with a query parameter
        $config = array_merge($this->config, ['auth' => ['enabled' => true, 'in' => 'query', 'name' => 'token']]);
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertCount(1, $results['components']['securitySchemes']);
        $this->assertArrayHasKey('default', $results['components']['securitySchemes']);
        $this->assertEquals('apiKey', $results['components']['securitySchemes']['default']['type']);
        $this->assertEquals($config['auth']['name'], $results['components']['securitySchemes']['default']['name']);
        $this->assertEquals('query', $results['components']['securitySchemes']['default']['in']);
        $this->assertCount(1, $results['security']);
        $this->assertCount(1, $results['security'][0]);
        $this->assertArrayHasKey('default', $results['security'][0]);
        $this->assertArrayNotHasKey('security', $results['paths']['/path1']['get']);
        $this->assertArrayHasKey('security', $results['paths']['/path1']['post']);
        $this->assertCount(0, $results['paths']['/path1']['post']['security']);
    }

    /** @test */
    public function adds_url_parameters_correctly_as_parameters_on_path_item_object()
    {
        $fakeRoute1 = $this->createMockRouteData([
            'methods' => ['POST'],
            'uri' => 'path1/{param}/{optionalParam?}',
            'urlParameters.param' => [
                'description' => 'Something',
                'required' => true,
                'value' => 56,
                'type' => 'integer',
            ],
            'urlParameters.optionalParam' => [
                'description' => 'Another',
                'required' => false,
                'value' => '69',
                'type' => 'string',
            ],
        ]);
        $fakeRoute2 = $this->createMockRouteData(['uri' => 'path1', 'methods' => ['POST']]);
        $groupedEndpoints = collect([$fakeRoute1, $fakeRoute2])->groupBy('metadata.groupName');

        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertArrayNotHasKey('parameters', $results['paths']['/path1']);
        $this->assertCount(2, $results['paths']['/path1/{param}/{optionalParam}']['parameters']);
        $this->assertEquals([
            'in' => 'path',
            'required' => true,
            'name' => 'param',
            'description' => 'Something',
            'example' => 56,
            'schema' => ['type' => 'integer'],
        ], $results['paths']['/path1/{param}/{optionalParam}']['parameters'][0]);
        $this->assertEquals([
            'in' => 'path',
            'required' => true,
            'name' => 'optionalParam',
            'description' => 'Optional parameter. Another',
            'examples' => [
                'omitted' => ['summary' => 'When the value is omitted', 'value' => ''],
                'present' => [
                    'summary' => 'When the value is present', 'value' => '69'],
            ],
            'schema' => ['type' => 'string'],
        ], $results['paths']['/path1/{param}/{optionalParam}']['parameters'][1]);
    }

    /** @test */
    public function adds_headers_correctly_as_parameters_on_operation_object()
    {
        $fakeRoute1 = $this->createMockRouteData(['methods' => ['POST'], 'uri' => 'path1', 'headers.Extra-Header' => 'Some-Value']);
        $fakeRoute2 = $this->createMockRouteData(['uri' => 'path1', 'methods' => ['GET'], 'headers' => []]);
        $groupedEndpoints = collect([$fakeRoute1, $fakeRoute2])->groupBy('metadata.groupName');

        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertEquals([], $results['paths']['/path1']['get']['parameters']);
        $this->assertCount(2, $results['paths']['/path1']['post']['parameters']);
        $this->assertEquals([
            'in' => 'header',
            'name' => 'Content-Type',
            'description' => '',
            'example' => 'application/json',
            'schema' => ['type' => 'string'],
        ], $results['paths']['/path1']['post']['parameters'][0]);
        $this->assertEquals([
            'in' => 'header',
            'name' => 'Extra-Header',
            'description' => '',
            'example' => 'Some-Value',
            'schema' => ['type' => 'string'],
        ], $results['paths']['/path1']['post']['parameters'][1]);
    }

    /** @test */
    public function adds_query_parameters_correctly_as_parameters_on_operation_object()
    {
        $fakeRoute1 = $this->createMockRouteData([
            'methods' => ['GET'],
            'uri' => '/path1',
            'headers' => [], // Emptying headers so it doesn't interfere with parameters object
            'queryParameters' => [
                'param' => [
                    'description' => 'A query param',
                    'required' => false,
                    'value' => 'hahoho',
                    'type' => 'string',
                ],
            ],
        ]);
        $fakeRoute2 = $this->createMockRouteData(['queryParameters' => [], 'headers' => [], 'methods' => ['POST'], 'uri' => '/path1',]);
        $groupedEndpoints = collect([$fakeRoute1, $fakeRoute2])->groupBy('metadata.groupName');

        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertEquals([], $results['paths']['/path1']['post']['parameters']);
        $this->assertArrayHasKey('parameters', $results['paths']['/path1']['get']);
        $this->assertCount(1, $results['paths']['/path1']['get']['parameters']);
        $this->assertEquals([
            'in' => 'query',
            'required' => false,
            'name' => 'param',
            'description' => 'A query param',
            'example' => 'hahoho',
            'schema' => [
                'type' => 'string',
                'description' => 'A query param',
                'example' => 'hahoho',
            ],
        ], $results['paths']['/path1']['get']['parameters'][0]);
    }

    /** @test */
    public function adds_body_parameters_correctly_as_requestBody_on_operation_object()
    {
        $fakeRoute1 = $this->createMockRouteData([
            'methods' => ['POST'],
            'uri' => '/path1',
            'bodyParameters' => [
                'stringParam' => [
                    'name' => 'stringParam',
                    'description' => 'String param',
                    'required' => false,
                    'value' => 'hahoho',
                    'type' => 'string',
                ],
                'integerParam' => [
                    'name' => 'integerParam',
                    'description' => 'Integer param',
                    'required' => true,
                    'value' => 99,
                    'type' => 'integer',
                ],
                'booleanParam' => [
                    'name' => 'booleanParam',
                    'description' => 'Boolean param',
                    'required' => true,
                    'value' => false,
                    'type' => 'boolean',
                ],
                'objectParam' => [
                    'name' => 'objectParam',
                    'description' => 'Object param',
                    'required' => false,
                    'value' => [],
                    'type' => 'object',
                ],
                'objectParam.field' => [
                    'name' => 'objectParam.field',
                    'description' => 'Object param field',
                    'required' => false,
                    'value' => 119.0,
                    'type' => 'number',
                ],
            ],
        ]);
        $fakeRoute1['nestedBodyParameters'] = Generator::nestArrayAndObjectFields($fakeRoute1['bodyParameters']);
        $fakeRoute2 = $this->createMockRouteData(['methods' => ['GET'], 'uri' => '/path1']);
        $fakeRoute3 = $this->createMockRouteData([
            'methods' => ['PUT'],
            'uri' => '/path2',
            'bodyParameters' => [
                'fileParam' => [
                    'name' => 'fileParam',
                    'description' => 'File param',
                    'required' => false,
                    'value' => null,
                    'type' => 'file',
                ],
                'numberArrayParam' => [
                    'name' => 'numberArrayParam',
                    'description' => 'Number array param',
                    'required' => false,
                    'value' => [186.9],
                    'type' => 'number[]',
                ],
                'objectArrayParam' => [
                    'name' => 'objectArrayParam',
                    'description' => 'Object array param',
                    'required' => false,
                    'value' => [[]],
                    'type' => 'object[]',
                ],
                'objectArrayParam[].field1' => [
                    'name' => 'objectArrayParam[].field1',
                    'description' => 'Object array param first field',
                    'required' => true,
                    'value' => ["hello"],
                    'type' => 'string[]',
                ],
                'objectArrayParam[].field2' => [
                    'name' => 'objectArrayParam[].field2',
                    'description' => '',
                    'required' => false,
                    'value' => "hi",
                    'type' => 'string',
                ],
            ],
        ]);
        $fakeRoute3['nestedBodyParameters'] = Generator::nestArrayAndObjectFields($fakeRoute3['bodyParameters']);
        $groupedEndpoints = collect([$fakeRoute1, $fakeRoute2, $fakeRoute3])->groupBy('metadata.groupName');

        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertArrayNotHasKey('requestBody', $results['paths']['/path1']['get']);
        $this->assertArrayHasKey('requestBody', $results['paths']['/path1']['post']);
        $this->assertEquals([
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'stringParam' => [
                                'description' => 'String param',
                                'example' => 'hahoho',
                                'type' => 'string',
                            ],
                            'booleanParam' => [
                                'description' => 'Boolean param',
                                'example' => false,
                                'type' => 'boolean',
                            ],
                            'integerParam' => [
                                'description' => 'Integer param',
                                'example' => 99,
                                'type' => 'integer',
                            ],
                            'objectParam' => [
                                'description' => 'Object param',
                                'example' => [],
                                'type' => 'object',
                                'properties' => [
                                    'field' => [
                                        'description' => 'Object param field',
                                        'example' => 119.0,
                                        'type' => 'number',
                                    ],
                                ],
                            ],
                        ],
                        'required' => [
                            'integerParam',
                            'booleanParam',
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['post']['requestBody']);
        $this->assertEquals([
            'required' => false,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'fileParam' => [
                                'description' => 'File param',
                                'type' => 'string',
                                'format' => 'binary',
                            ],
                            'numberArrayParam' => [
                                'description' => 'Number array param',
                                'example' => [186.9],
                                'type' => 'array',
                                'items' => [
                                    'type' => 'number',
                                ],
                            ],
                            'objectArrayParam' => [
                                'description' => 'Object array param',
                                'example' => [[]],
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['field1'],
                                    'properties' => [
                                        'field1' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'string'
                                            ],
                                            'description' => 'Object array param first field',
                                            'example' => ["hello"],
                                        ],
                                        'field2' => [
                                            'type' => 'string',
                                            'description' => '',
                                            'example' => "hi",
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path2']['put']['requestBody']);
    }

    /** @test */
    public function adds_responses_correctly_as_responses_on_operation_object()
    {
        $fakeRoute1 = $this->createMockRouteData([
            'methods' => ['POST'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => '204',
                    'description' => 'Successfully updated.',
                    'content' => '{"this": "should be ignored"}',
                ],
                [
                    'status' => '201',
                    'description' => '',
                    'content' => '{"this": "shouldn\'t be ignored", "and this": "too"}',
                ],
            ],
            'responseFields' => [
                'and this' => [
                    'type' => 'string',
                    'description' => 'Parameter description, ha!',
                ],
            ],
        ]);
        $fakeRoute2 = $this->createMockRouteData([
            'methods' => ['PUT'],
            'uri' => '/path2',
            'responses' => [
                [
                    'status' => '200',
                    'description' => '',
                    'content' => '<<binary>> The cropped image',
                ],
            ],
        ]);
        $groupedEndpoints = collect([$fakeRoute1, $fakeRoute2])->groupBy('metadata.groupName');

        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertCount(2, $results['paths']['/path1']['post']['responses']);
        $this->assertArraySubset([
            '204' => [
                'description' => 'Successfully updated.',
            ],
            '201' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'this' => [
                                    'example' => "shouldn't be ignored",
                                    'type' => 'string',
                                ],
                                'and this' => [
                                    'description' => 'Parameter description, ha!',
                                    'example' => "too",
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['post']['responses']);
        $this->assertCount(1, $results['paths']['/path2']['put']['responses']);
        $this->assertEquals([
            '200' => [
                'description' => 'The cropped image',
                'content' => [
                    'application/octet-stream' => [
                        'schema' => [
                            'type' => 'string',
                            'format' => 'binary',
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path2']['put']['responses']);
    }

    protected function createMockRouteData(array $custom = [])
    {
        $faker = Factory::create();
        $data = [
            'uri' => '/' . $faker->word,
            'methods' => $faker->randomElements(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 1),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'metadata' => [
                'groupDescription' => '',
                'groupName' => $faker->randomElement(['Endpoints', 'Group A', 'Group B']),
                'title' => $faker->sentence,
                'description' => $faker->randomElement([$faker->sentence, '']),
                'authenticated' => $faker->boolean,
            ],
            'urlParameters' => [], // Should be set by caller (along with custom path)
            'queryParameters' => [],
            'bodyParameters' => [],
            'responses' => [
                [
                    'status' => 200,
                    'content' => '{"random": "json"}',
                    'description' => 'Okayy',
                ],
            ],
            'responseFields' => [],
        ];

        foreach ($custom as $key => $value) {
            data_set($data, $key, $value);
        }

        return $data;
    }
}
