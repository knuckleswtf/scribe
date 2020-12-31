<?php

namespace Knuckles\Scribe\Tests\Unit;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Faker\Factory;
use Illuminate\Routing\Route;
use Knuckles\Camel\Endpoint\BodyParameter;
use Knuckles\Camel\Endpoint\EndpointData;
use Knuckles\Camel\Endpoint\QueryParameter;
use Knuckles\Camel\Endpoint\ResponseCollection;
use Knuckles\Camel\Endpoint\ResponseField;
use Knuckles\Camel\Endpoint\UrlParameter;
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
        $endpointData1 = $this->createMockEndpointData();
        $endpointData2 = $this->createMockEndpointData();
        $groupedEndpoints = collect([$endpointData1, $endpointData2])->groupBy('metadata.groupName');

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
        $endpointData1 = $this->createMockEndpointData(['uri' => 'path1', 'methods' => ['GET']]);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path1', 'methods' => ['POST']]);
        $endpointData3 = $this->createMockEndpointData(['uri' => 'path1/path2']);
        $groupedEndpoints = collect([$endpointData1, $endpointData2, $endpointData3])->groupBy('metadata.groupName');

        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));
        $results = $writer->generateSpecContent($groupedEndpoints);

        $this->assertIsArray($results['paths']);
        $this->assertCount(2, $results['paths']);
        $this->assertCount(2, $results['paths']['/path1']);
        $this->assertCount(1, $results['paths']['/path1/path2']);
        $this->assertArrayHasKey('get', $results['paths']['/path1']);
        $this->assertArrayHasKey('post', $results['paths']['/path1']);
        $this->assertArrayHasKey(strtolower($endpointData3->methods[0]), $results['paths']['/path1/path2']);

        collect([$endpointData1, $endpointData2, $endpointData3])->each(function (EndpointData $endpoint) use ($results) {
            $method = strtolower($endpoint->methods[0]);
            $this->assertEquals([$endpoint->metadata->groupName], $results['paths']['/' . $endpoint->uri][$method]['tags']);
            $this->assertEquals($endpoint->metadata->title, $results['paths']['/' . $endpoint->uri][$method]['summary']);
            $this->assertEquals($endpoint->metadata->description, $results['paths']['/' . $endpoint->uri][$method]['description']);
        });
    }

    /** @test */
    public function adds_authentication_details_correctly_as_security_info()
    {
        $endpointData1 = $this->createMockEndpointData(['uri' => 'path1', 'methods' => ['GET'], 'metadata.authenticated' => true]);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path1', 'methods' => ['POST'], 'metadata.authenticated' => false]);
        $groupedEndpoints = collect([$endpointData1,$endpointData2])->groupBy('metadata.groupName');

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
        $endpointData1 = $this->createMockEndpointData([
            'methods' => ['POST'],
            'uri' => 'path1/{param}/{optionalParam?}',
            'urlParameters.param' => new UrlParameter([
                'description' => 'Something',
                'required' => true,
                'example' => 56,
                'type' => 'integer',
                'name' => 'param',
            ]),
            'urlParameters.optionalParam' => new UrlParameter([
                'description' => 'Another',
                'required' => false,
                'example' => '69',
                'type' => 'string',
                'name' => 'optionalParam',
            ]),
        ]);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path1', 'methods' => ['POST']]);
        $groupedEndpoints = collect([$endpointData1, $endpointData2])->groupBy('metadata.groupName');

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
        $endpointData1 = $this->createMockEndpointData(['methods' => ['POST'], 'uri' => 'path1', 'headers.Extra-Header' => 'Some-example']);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path1', 'methods' => ['GET'], 'headers' => []]);
        $groupedEndpoints = collect([$endpointData1, $endpointData2])->groupBy('metadata.groupName');

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
            'example' => 'Some-example',
            'schema' => ['type' => 'string'],
        ], $results['paths']['/path1']['post']['parameters'][1]);
    }

    /** @test */
    public function adds_query_parameters_correctly_as_parameters_on_operation_object()
    {
        $endpointData1 = $this->createMockEndpointData([
            'methods' => ['GET'],
            'uri' => '/path1',
            'headers' => [], // Emptying headers so it doesn't interfere with parameters object
            'queryParameters' => [
                'param' => new QueryParameter([
                    'description' => 'A query param',
                    'required' => false,
                    'example' => 'hahoho',
                    'type' => 'string',
                    'name' => 'param',
                ]),
            ],
        ]);
        $endpointData2 = $this->createMockEndpointData(['headers' => [], 'methods' => ['POST'], 'uri' => '/path1',]);
        $groupedEndpoints = collect([$endpointData1, $endpointData2])->groupBy('metadata.groupName');

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
        $endpointData1 = $this->createMockEndpointData([
            'methods' => ['POST'],
            'uri' => '/path1',
            'bodyParameters' => BodyParameter::arrayOf([
                'stringParam' => [
                    'name' => 'stringParam',
                    'description' => 'String param',
                    'required' => false,
                    'example' => 'hahoho',
                    'type' => 'string',
                ],
                'integerParam' => [
                    'name' => 'integerParam',
                    'description' => 'Integer param',
                    'required' => true,
                    'example' => 99,
                    'type' => 'integer',
                ],
                'booleanParam' => [
                    'name' => 'booleanParam',
                    'description' => 'Boolean param',
                    'required' => true,
                    'example' => false,
                    'type' => 'boolean',
                ],
                'objectParam' => [
                    'name' => 'objectParam',
                    'description' => 'Object param',
                    'required' => false,
                    'example' => [],
                    'type' => 'object',
                ],
                'objectParam.field' => [
                    'name' => 'objectParam.field',
                    'description' => 'Object param field',
                    'required' => false,
                    'example' => 119.0,
                    'type' => 'number',
                ],
            ]),
        ]);
        $endpointData1->nestedBodyParameters = Generator::nestArrayAndObjectFields($endpointData1->bodyParameters);
        $endpointData2 = $this->createMockEndpointData(['methods' => ['GET'], 'uri' => '/path1']);
        $endpointData3 = $this->createMockEndpointData([
            'methods' => ['PUT'],
            'uri' => '/path2',
            'bodyParameters' => BodyParameter::arrayOf([
                'fileParam' => [
                    'name' => 'fileParam',
                    'description' => 'File param',
                    'required' => false,
                    'example' => null,
                    'type' => 'file',
                ],
                'numberArrayParam' => [
                    'name' => 'numberArrayParam',
                    'description' => 'Number array param',
                    'required' => false,
                    'example' => [186.9],
                    'type' => 'number[]',
                ],
                'objectArrayParam' => [
                    'name' => 'objectArrayParam',
                    'description' => 'Object array param',
                    'required' => false,
                    'example' => [[]],
                    'type' => 'object[]',
                ],
                'objectArrayParam[].field1' => [
                    'name' => 'objectArrayParam[].field1',
                    'description' => 'Object array param first field',
                    'required' => true,
                    'example' => ["hello"],
                    'type' => 'string[]',
                ],
                'objectArrayParam[].field2' => [
                    'name' => 'objectArrayParam[].field2',
                    'description' => '',
                    'required' => false,
                    'example' => "hi",
                    'type' => 'string',
                ],
            ]),
        ]);
        $endpointData3->nestedBodyParameters = Generator::nestArrayAndObjectFields($endpointData3->bodyParameters);
        $groupedEndpoints = collect([$endpointData1, $endpointData2, $endpointData3])->groupBy('metadata.groupName');

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
        $endpointData1 = $this->createMockEndpointData([
            'methods' => ['POST'],
            'uri' => '/path1',
            'responses' => new ResponseCollection([
                [
                    'status' => 204,
                    'description' => 'Successfully updated.',
                    'content' => '{"this": "should be ignored"}',
                ],
                [
                    'status' => 201,
                    'description' => '',
                    'content' => '{"this": "shouldn\'t be ignored", "and this": "too"}',
                ],
            ]),
            'responseFields' => ResponseField::arrayOf([
                'and this' => [
                    'name' => 'and this',
                    'type' => 'string',
                    'description' => 'Parameter description, ha!',
                ],
            ]),
        ]);
        $endpointData2 = $this->createMockEndpointData([
            'methods' => ['PUT'],
            'uri' => '/path2',
            'responses' => new ResponseCollection([
                [
                    'status' => 200,
                    'description' => '',
                    'content' => '<<binary>> The cropped image',
                ],
            ]),
        ]);
        $groupedEndpoints = collect([$endpointData1, $endpointData2])->groupBy('metadata.groupName');

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

    protected function createMockEndpointData(array $custom = []): EndpointData
    {
        $faker = Factory::create();
        $path = '/' . $faker->word;
        $data = [
            'uri' => $path,
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
            'responses' => new ResponseCollection([
                [
                    'status' => 200,
                    'content' => '{"random": "json"}',
                    'description' => 'Okayy',
                ],
            ]),
            'responseFields' => [],
            'route' => new Route(['GET'], $path, []),
        ];

        foreach ($custom as $key => $value) {
            data_set($data, $key, $value);
        }

        return new EndpointData($data);
    }
}
