<?php

namespace Knuckles\Scribe\Tests\Unit;

use Faker\Factory;
use Illuminate\Support\Arr;
use Knuckles\Camel\Camel;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tests\BaseUnitTest;
use Knuckles\Scribe\Tests\Fixtures\ComponentsOpenApiGenerator;
use Knuckles\Scribe\Tests\Fixtures\TestOpenApiGenerator;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\OpenAPISpecWriter;

/**
 * See https://swagger.io/specification/.
 *
 * @internal
 *
 * @coversNothing
 */
class OpenAPISpecWriterTest extends BaseUnitTest
{
    protected $config = [
        'title' => 'My Testy Testes API',
        'description' => 'All about testy testes.',
        'base_url' => 'http://api.api.dev',
    ];

    /** @test */
    public function followsCorrectSpecStructure()
    {
        $endpointData1 = $this->createMockEndpointData();
        $endpointData2 = $this->createMockEndpointData();
        $groups = [$this->createGroup([$endpointData1, $endpointData2])];

        $results = $this->generate($groups);

        $this->assertEquals(OpenAPISpecWriter::SPEC_VERSION, $results['openapi']);
        $this->assertEquals($this->config['title'], $results['info']['title']);
        $this->assertEquals($this->config['description'], $results['info']['description']);
        $this->assertNotEmpty($results['info']['version']);
        $this->assertEquals($this->config['base_url'], $results['servers'][0]['url']);
        $this->assertIsArray($results['paths']);
        $this->assertGreaterThan(0, count($results['paths']));
    }

    /** @test */
    public function addsEndpointsCorrectlyAsOperationsUnderPaths()
    {
        $endpointData1 = $this->createMockEndpointData(['uri' => 'path1', 'httpMethods' => ['GET']]);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path1', 'httpMethods' => ['POST']]);
        $endpointData3 = $this->createMockEndpointData(['uri' => 'path1/path2']);
        $groups = [$this->createGroup([$endpointData1, $endpointData2, $endpointData3])];

        $results = $this->generate($groups);

        $this->assertIsArray($results['paths']);
        $this->assertCount(2, $results['paths']);
        $this->assertCount(2, $results['paths']['/path1']);
        $this->assertCount(1, $results['paths']['/path1/path2']);
        $this->assertArrayHasKey('get', $results['paths']['/path1']);
        $this->assertArrayHasKey('post', $results['paths']['/path1']);
        $this->assertArrayHasKey(strtolower($endpointData3->httpMethods[0]), $results['paths']['/path1/path2']);

        collect([$endpointData1, $endpointData2, $endpointData3])->each(function (OutputEndpointData $endpoint) use ($groups, $results) {
            $endpointSpec = $results['paths']['/' . $endpoint->uri][strtolower($endpoint->httpMethods[0])];

            $tags = $endpointSpec['tags'];
            $containingGroup = Arr::first($groups, function ($group) use ($endpoint) {
                return Camel::doesGroupContainEndpoint($group, $endpoint);
            });
            $this->assertEquals([$containingGroup['name']], $tags);

            $this->assertEquals($endpoint->metadata->title, $endpointSpec['summary']);
            $this->assertEquals($endpoint->metadata->description, $endpointSpec['description']);
        });
    }

    /** @test */
    public function addsAuthenticationDetailsCorrectlyAsSecurityInfo()
    {
        $endpointData1 = $this->createMockEndpointData(['uri' => 'path1', 'httpMethods' => ['GET'], 'metadata.authenticated' => true]);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path1', 'httpMethods' => ['POST'], 'metadata.authenticated' => false]);
        $groups = [$this->createGroup([$endpointData1, $endpointData2])];
        $extraInfo = 'When stuck trying to authenticate, have a coffee!';
        $config = array_merge($this->config, [
            'auth' => [
                'enabled' => true,
                'in' => 'bearer',
                'extra_info' => $extraInfo,
            ],
        ]);
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));
        $results = $writer->generateSpecContent($groups);

        $this->assertCount(1, $results['components']['securitySchemes']);
        $this->assertArrayHasKey('default', $results['components']['securitySchemes']);
        $this->assertEquals('http', $results['components']['securitySchemes']['default']['type']);
        $this->assertEquals('bearer', $results['components']['securitySchemes']['default']['scheme']);
        $this->assertEquals($extraInfo, $results['components']['securitySchemes']['default']['description']);
        $this->assertCount(1, $results['security']);
        $this->assertCount(1, $results['security'][0]);
        $this->assertArrayHasKey('default', $results['security'][0]);
        $this->assertArrayNotHasKey('security', $results['paths']['/path1']['get']);
        $this->assertArrayHasKey('security', $results['paths']['/path1']['post']);
        $this->assertCount(0, $results['paths']['/path1']['post']['security']);

        // Next try: auth with a query parameter
        $config = array_merge($this->config, [
            'auth' => [
                'enabled' => true,
                'in' => 'query',
                'name' => 'token',
                'extra_info' => $extraInfo,
            ],
        ]);
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));
        $results = $writer->generateSpecContent($groups);

        $this->assertCount(1, $results['components']['securitySchemes']);
        $this->assertArrayHasKey('default', $results['components']['securitySchemes']);
        $this->assertEquals('apiKey', $results['components']['securitySchemes']['default']['type']);
        $this->assertEquals($extraInfo, $results['components']['securitySchemes']['default']['description']);
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
    public function addsDeprecationInfoCorrectly()
    {
        $endpointData1 = $this->createMockEndpointData(['uri' => 'path1', 'httpMethods' => ['GET'], 'metadata.deprecated' => true]);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path2', 'httpMethods' => ['GET'], 'metadata.deprecated' => false]);
        $groups = [$this->createGroup([$endpointData1, $endpointData2])];

        $results = $this->generate($groups);

        $this->assertIsArray($results['paths']);
        $this->assertCount(2, $results['paths']);
        $this->assertArrayHasKey('deprecated', $results['paths']['/path1']['get']);
        $this->assertTrue($results['paths']['/path1']['get']['deprecated']);
        $this->assertArrayNotHasKey('deprecated', $results['paths']['/path2']['get']);
    }

    /** @test */
    public function addsDeprecatedParamsInfoCorrectly()
    {
        $endpointData1 = $this->createMockEndpointData([
            'uri' => 'path1',
            'httpMethods' => ['GET'],
            'queryParameters' => [
                'param' => [
                    'description' => 'A query param',
                    'required' => false,
                    'example' => 'hahoho',
                    'type' => 'string',
                    'name' => 'param',
                    'deprecated' => true,
                ],
            ],
        ]);
        $endpointData2 = $this->createMockEndpointData([
            'uri' => 'path2',
            'httpMethods' => ['POST'],
            'bodyParameters' => [
                'param' => [
                    'description' => 'A body param',
                    'required' => false,
                    'example' => 'hahoho',
                    'type' => 'string',
                    'name' => 'param',
                    'deprecated' => true,
                ],
                'array_param' => [
                    'description' => 'A body array_param',
                    'required' => false,
                    'type' => 'array',
                    'name' => 'array_param',
                    'deprecated' => true,
                ],
                'object_param' => [
                    'description' => 'A body object_param',
                    'required' => false,
                    'type' => 'object',
                    'name' => 'object_param',
                    'deprecated' => true,
                ],
            ],
        ]);
        $groups = [$this->createGroup([$endpointData1, $endpointData2])];

        $results = $this->generate($groups);

        $this->assertIsArray($results['paths']);
        $this->assertCount(2, $results['paths']);
        $this->assertTrue($results['paths']['/path1']['get']['parameters'][0]['deprecated']);

        $properties = $results['paths']['/path2']['post']['requestBody']['content']['application/json']['schema']['properties'];
        $this->assertCount(3, $properties);
        $this->assertTrue($properties['param']['deprecated']);
        $this->assertTrue($properties['array_param']['deprecated']);
        $this->assertTrue($properties['object_param']['deprecated']);
    }

    /** @test */
    public function addsUrlParametersCorrectlyAsParametersOnPathItemObject()
    {
        $endpointData1 = $this->createMockEndpointData([
            'httpMethods' => ['POST'],
            'uri' => 'path1/{param}/{optionalParam?}',
            'urlParameters.param' => [
                'description' => 'Something',
                'required' => true,
                'example' => 56,
                'type' => 'integer',
                'name' => 'param',
            ],
            'urlParameters.optionalParam' => [
                'description' => 'Another',
                'required' => false,
                'example' => '69',
                'type' => 'string',
                'name' => 'optionalParam',
            ],
        ]);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path1', 'httpMethods' => ['POST']]);
        $groups = [$this->createGroup([$endpointData1, $endpointData2])];

        $results = $this->generate($groups);

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
    public function addsHeadersCorrectlyAsParametersOnOperationObject()
    {
        $endpointData1 = $this->createMockEndpointData(['httpMethods' => ['POST'], 'uri' => 'path1', 'headers.Extra-Header' => 'Some-example']);
        $endpointData2 = $this->createMockEndpointData(['uri' => 'path1', 'httpMethods' => ['GET'], 'headers' => []]);
        $groups = [$this->createGroup([$endpointData1, $endpointData2])];

        $results = $this->generate($groups);

        $this->assertEquals([], $results['paths']['/path1']['get']['parameters']);
        $this->assertCount(1, $results['paths']['/path1']['post']['parameters']);
        $this->assertEquals([
            'in' => 'header',
            'name' => 'Extra-Header',
            'description' => '',
            'example' => 'Some-example',
            'schema' => ['type' => 'string'],
        ], $results['paths']['/path1']['post']['parameters'][0]);
    }

    /** @test */
    public function addsQueryParametersCorrectlyAsParametersOnOperationObject()
    {
        $endpointData1 = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/path1',
            'headers' => [], // Emptying headers so it doesn't interfere with parameters object
            'queryParameters' => [
                'param' => [
                    'description' => 'A query param',
                    'required' => false,
                    'example' => 'hahoho',
                    'type' => 'string',
                    'name' => 'param',
                ],
            ],
        ]);
        $endpointData2 = $this->createMockEndpointData(['headers' => [], 'httpMethods' => ['POST'], 'uri' => '/path1']);
        $groups = [$this->createGroup([$endpointData1, $endpointData2])];

        $results = $this->generate($groups);

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
    public function addsBodyParametersCorrectlyAsRequestBodyOnOperationObject()
    {
        $endpointData1 = $this->createMockEndpointData([
            'httpMethods' => ['POST'],
            'uri' => '/path1',
            'bodyParameters' => [
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
            ],
        ]);
        $endpointData2 = $this->createMockEndpointData(['httpMethods' => ['GET'], 'uri' => '/path1']);
        $endpointData3 = $this->createMockEndpointData([
            'httpMethods' => ['PUT'],
            'uri' => '/path2',
            'bodyParameters' => [
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
                    'example' => ['hello'],
                    'type' => 'string[]',
                ],
                'objectArrayParam[].field2' => [
                    'name' => 'objectArrayParam[].field2',
                    'description' => '',
                    'required' => false,
                    'example' => 'hi',
                    'type' => 'string',
                ],
            ],
        ]);
        $groups = [$this->createGroup([$endpointData1, $endpointData2, $endpointData3])];

        $results = $this->generate($groups);

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
                                                'type' => 'string',
                                            ],
                                            'description' => 'Object array param first field',
                                            'example' => ['hello'],
                                        ],
                                        'field2' => [
                                            'type' => 'string',
                                            'description' => '',
                                            'example' => 'hi',
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
    public function addsResponsesCorrectlyAsResponsesOnOperationObject()
    {
        $endpointData1 = $this->createMockEndpointData([
            'httpMethods' => ['POST'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => 204,
                    'description' => 'Successfully updated.',
                    'content' => '{"this": "should be ignored"}',
                ],
                [
                    'status' => 201,
                    'description' => '',
                    'content' => '{"this": "shouldn\'t be ignored", "and this": "too", "also this": "too", "sub level 0": { "sub level 1 key 1": "sl0_sl1k1", "sub level 1 key 2": [ { "sub level 2 key 1": "sl0_sl1k2_sl2k1", "sub level 2 key 2": { "sub level 3 key 1": "sl0_sl1k2_sl2k2_sl3k1" } } ], "sub level 1 key 3": { "sub level 2 key 1": "sl0_sl1k3_sl2k2", "sub level 2 key 2": { "sub level 3 key 1": "sl0_sl1k3_sl2k2_sl3k1", "sub level 3 key null": null, "sub level 3 key integer": 99 }, "sub level 2 key 3 required" : "sl0_sl1k3_sl2k3" } } }',
                ],
            ],
            'responseFields' => [
                'and this' => [
                    'name' => 'and this',
                    'type' => 'string',
                    'description' => 'Parameter description, ha!',
                ],
                'also this' => [
                    'name' => 'also this',
                    'type' => 'string',
                    'description' => 'This response parameter is required.',
                    'required' => true,
                ],
                'sub level 0.sub level 1 key 3.sub level 2 key 1' => [
                    'description' => 'This is a description of a nested object',
                ],
                'sub level 0.sub level 1 key 3.sub level 2 key 3 required' => [
                    'description' => 'This is a description of a required nested object',
                    'required' => true,
                ],
            ],
        ]);
        $endpointData2 = $this->createMockEndpointData([
            'httpMethods' => ['PUT'],
            'uri' => '/path2',
            'responses' => [
                [
                    'status' => 200,
                    'description' => '',
                    'content' => '<<binary>> The cropped image',
                ],
            ],
        ]);
        $groups = [$this->createGroup([$endpointData1, $endpointData2])];

        $results = $this->generate($groups);

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
                                    'example' => 'too',
                                    'type' => 'string',
                                ],
                                'also this' => [
                                    'description' => 'This response parameter is required.',
                                    'example' => 'too',
                                    'type' => 'string',
                                ],
                                'sub level 0' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'sub level 1 key 1' => [
                                            'type' => 'string',
                                            'example' => 'sl0_sl1k1',
                                        ],
                                        'sub level 1 key 2' => [
                                            'type' => 'array',
                                            'example' => [
                                                [
                                                    'sub level 2 key 1' => 'sl0_sl1k2_sl2k1',
                                                    'sub level 2 key 2' => [
                                                        'sub level 3 key 1' => 'sl0_sl1k2_sl2k2_sl3k1',
                                                    ],
                                                ],
                                            ],
                                            'items' => [
                                                'type' => 'object',
                                            ],
                                        ],
                                        'sub level 1 key 3' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'sub level 2 key 1' => [
                                                    'type' => 'string',
                                                    'example' => 'sl0_sl1k3_sl2k2',
                                                    'description' => 'This is a description of a nested object',
                                                ],
                                                'sub level 2 key 2' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'sub level 3 key 1' => [
                                                            'type' => 'string',
                                                            'example' => 'sl0_sl1k3_sl2k2_sl3k1',
                                                        ],
                                                        'sub level 3 key null' => [
                                                            'type' => 'string',
                                                            'example' => null,
                                                        ],
                                                        'sub level 3 key integer' => [
                                                            'type' => 'integer',
                                                            'example' => 99,
                                                        ],
                                                    ],
                                                ],
                                                'sub level 2 key 3 required' => [
                                                    'type' => 'string',
                                                    'example' => 'sl0_sl1k3_sl2k3',
                                                    'description' => 'This is a description of a required nested object',
                                                ],
                                            ],
                                            'required' => [
                                                'sub level 2 key 3 required',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'required' => [
                                'also this',
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

    /** @test */
    public function appliesRequiredFlagForNestedResponseFieldsWithDotNotation()
    {
        $endpointData = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/api/resource',
            'responses' => [
                [
                    'status' => 200,
                    'description' => '',
                    'content' => json_encode([
                        'status' => [
                            'technicalValue' => 'active',
                            'displayValue' => 'Active',
                        ],
                    ]),
                ],
            ],
            'responseFields' => [
                'status.technicalValue' => [
                    'name' => 'status.technicalValue',
                    'type' => 'string',
                    'description' => 'The technical status value',
                    'required' => true,
                ],
                'status.displayValue' => [
                    'name' => 'status.displayValue',
                    'type' => 'string',
                    'description' => 'The display status value',
                    'required' => false,
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData])];
        $results = $this->generate($groups);

        $statusSchema = $results['paths']['/api/resource']['get']['responses']['200']['content']['application/json']['schema']['properties']['status'];

        $this->assertEquals('object', $statusSchema['type']);
        $this->assertArrayHasKey('properties', $statusSchema);
        $this->assertArrayHasKey('technicalValue', $statusSchema['properties']);
        $this->assertArrayHasKey('displayValue', $statusSchema['properties']);
        $this->assertArrayHasKey('required', $statusSchema);
        $this->assertContains('technicalValue', $statusSchema['required']);
        $this->assertNotContains('displayValue', $statusSchema['required']);
    }

    /** @test */
    public function appliesRequiredFlagForNestedResponseFieldsFromApiResourcesWithDataPrefix()
    {
        $endpointData = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/api/resource',
            'responses' => [
                [
                    'status' => 200,
                    'description' => '',
                    'content' => json_encode([
                        'data' => [
                            'status' => [
                                'technicalValue' => 'active',
                                'displayValue' => 'Active',
                            ],
                        ],
                    ]),
                ],
            ],
            'responseFields' => [
                'data.status.technicalValue' => [
                    'name' => 'data.status.technicalValue',
                    'type' => 'string',
                    'description' => 'The technical status value',
                    'required' => true,
                ],
                'data.status.displayValue' => [
                    'name' => 'data.status.displayValue',
                    'type' => 'string',
                    'description' => 'The display status value',
                    'required' => false,
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData])];
        $results = $this->generate($groups);

        // For API Resources, the response is wrapped in 'data', so we need to check data.status
        $dataSchema = $results['paths']['/api/resource']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'];
        $statusSchema = $dataSchema['properties']['status'];

        $this->assertEquals('object', $statusSchema['type']);
        $this->assertArrayHasKey('properties', $statusSchema);
        $this->assertArrayHasKey('technicalValue', $statusSchema['properties']);
        $this->assertArrayHasKey('displayValue', $statusSchema['properties']);
        $this->assertArrayHasKey('required', $statusSchema);
        $this->assertContains('technicalValue', $statusSchema['required']);
        $this->assertNotContains('displayValue', $statusSchema['required']);
    }

    /** @test */
    public function handlesRequiredParamsCorrectlyForNestedArrays()
    {
        $endpointData = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/api/scribe-test',
            'metadata' => [
                'title' => 'Scribe TEST',
            ],
            'responses' => [
                [
                    'status' => 200,
                    'description' => '',
                    'content' => json_encode([
                        'data' => [
                            'outer1' => [
                                'inner1' => 'string',
                            ],
                            'outer2' => [
                                'inner2' => 'string',
                            ],
                        ],
                    ]),
                ],
            ],
            'responseFields' => [
                'data.outer1' => [
                    'name' => 'data.outer1',
                    'description' => '',
                    'required' => true,
                    'type' => 'object',
                ],
                'data.outer1.inner1' => [
                    'name' => 'data.outer1.inner1',
                    'description' => '',
                    'required' => true,
                    'type' => 'string',
                ],
                'data.outer2' => [
                    'name' => 'data.outer2',
                    'description' => '',
                    'required' => true,
                    'type' => 'object',
                ],
                'data.outer2.inner2' => [
                    'name' => 'data.outer2.inner2',
                    'description' => '',
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData])];
        $results = $this->generate($groups);

        $dataSchema = $results['paths']['/api/scribe-test']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'];

        // outer1
        $this->assertEquals('object', $dataSchema['properties']['outer1']['type']);
        $this->assertContains('outer1', $dataSchema['required']);
        // outer1.inner1
        $this->assertEquals('string', $dataSchema['properties']['outer1']['properties']['inner1']['type']);
        $this->assertContains('inner1', $dataSchema['properties']['outer1']['required']);

        // outer2
        $this->assertEquals('object', $dataSchema['properties']['outer2']['type']);
        $this->assertContains('outer2', $dataSchema['required']);
        // outer2.inner2
        $this->assertEquals('string', $dataSchema['properties']['outer2']['properties']['inner2']['type']);
        $this->assertContains('inner2', $dataSchema['properties']['outer2']['required']);
    }

    /** @test */
    public function addsResponsesCorrectlyAsArrayOfObjects()
    {
        $endpointData1 = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => 200,
                    'description' => 'Successfully.',
                    'content' => '[{"id": 1, "name": "John"}, {"id": 2, "name": "Jane"}]',
                ],
            ],
        ]);
        $groups = [$this->createGroup([$endpointData1])];

        $results = $this->generate($groups);

        $this->assertCount(1, $results['paths']['/path1']['get']['responses']);
        $this->assertArraySubset([
            '200' => [
                'description' => 'Successfully.',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => [
                                        'type' => 'integer',
                                        'example' => 1,
                                    ],
                                    'name' => [
                                        'type' => 'string',
                                        'example' => 'John',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['get']['responses']);
    }

    /** @test */
    public function addsResponseContentTypeCorrectly()
    {
        $endpointData1 = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => 404,
                    'description' => 'No Found',
                    'content' => '{"this": "shouldn\'t be ignored"}',
                    'headers' => [
                        'Content-Type' => 'application/problem+json',
                    ],
                ],
            ],
        ]);
        $endpointData2 = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/path2',
            'responses' => [
                [
                    'status' => 404,
                    'description' => 'No Found',
                    'content' => '{"this": "shouldn\'t be ignored"}',
                    'headers' => [
                        'content-type' => 'application/problem+json',
                    ],
                ],
            ],
        ]);
        $endpointData3 = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/path3',
            'responses' => [
                [
                    'status' => 404,
                    'description' => 'No Found',
                    'content' => '{"this": "shouldn\'t be ignored"}',
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData1, $endpointData2, $endpointData3])];
        $results = $this->generate($groups);

        $this->assertCount(1, $results['paths']['/path1']['get']['responses']);
        $this->assertArraySubset([
            '404' => [
                'description' => 'No Found',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'this' => [
                                    'example' => "shouldn't be ignored",
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['get']['responses']);

        $this->assertCount(1, $results['paths']['/path2']['get']['responses']);
        $this->assertArraySubset([
            '404' => [
                'description' => 'No Found',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'this' => [
                                    'example' => "shouldn't be ignored",
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path2']['get']['responses']);

        $this->assertCount(1, $results['paths']['/path3']['get']['responses']);
        $this->assertArraySubset([
            '404' => [
                'description' => 'No Found',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'this' => [
                                    'example' => "shouldn't be ignored",
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path3']['get']['responses']);
    }

    /** @test */
    public function handlesCustomContentTypeWithVariousResponseBodyTypes()
    {
        $customJsonType = 'application/vnd.api+json';

        $endpointWithArray = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/array-response',
            'responses' => [[
                'status' => 200,
                'content' => '["foo", "bar"]',
                'headers' => ['Content-Type' => $customJsonType],
            ]],
        ]);

        $endpointWithString = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/string-response',
            'responses' => [[
                'status' => 200,
                'content' => '"a simple string"',
                'headers' => ['Content-Type' => $customJsonType],
            ]],
        ]);

        $endpointWithInteger = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/integer-response',
            'responses' => [[
                'status' => 200,
                'content' => '123',
                'headers' => ['Content-Type' => $customJsonType],
            ]],
        ]);

        $groups = [$this->createGroup([$endpointWithArray, $endpointWithString, $endpointWithInteger])];
        $results = $this->generate($groups);

        $arrayResponseSpec = $results['paths']['/array-response']['get']['responses']['200'];
        $this->assertArrayHasKey($customJsonType, $arrayResponseSpec['content']);
        $this->assertEquals('array', $arrayResponseSpec['content'][$customJsonType]['schema']['type']);
        $this->assertEquals(['foo', 'bar'], $arrayResponseSpec['content'][$customJsonType]['schema']['example']);

        $stringResponseSpec = $results['paths']['/string-response']['get']['responses']['200'];
        $this->assertArrayHasKey($customJsonType, $stringResponseSpec['content']);
        $this->assertEquals('string', $stringResponseSpec['content'][$customJsonType]['schema']['type']);
        $this->assertEquals('a simple string', $stringResponseSpec['content'][$customJsonType]['schema']['example']);

        $integerResponseSpec = $results['paths']['/integer-response']['get']['responses']['200'];
        $this->assertArrayHasKey($customJsonType, $integerResponseSpec['content']);
        $this->assertEquals('integer', $integerResponseSpec['content'][$customJsonType]['schema']['type']);
        $this->assertEquals(123, $integerResponseSpec['content'][$customJsonType]['schema']['example']);
    }

    /** @test */
    public function handlesNonJsonResponseContentAsTextPlain()
    {
        $endpoint = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/text-response',
            'responses' => [[
                'status' => 200,
                'content' => 'This is a simple text response.',
            ]],
        ]);

        $groups = [$this->createGroup([$endpoint])];
        $results = $this->generate($groups);

        $responseSpec = $results['paths']['/text-response']['get']['responses']['200'];
        $this->assertArrayHasKey('text/plain', $responseSpec['content']);
        $this->assertEquals('string', $responseSpec['content']['text/plain']['schema']['type']);
        $this->assertEquals('This is a simple text response.', $responseSpec['content']['text/plain']['schema']['example']);
    }

    /** @test */
    public function handlesNullAndEmptyArrayResponseContent()
    {
        $endpointWithNullContent = $this->createMockEndpointData([
            'uri' => '/null-response',
            'httpMethods' => ['GET'],
            'responses' => [[
                'status' => 200,
                'content' => null,
            ]],
        ]);

        $endpointWithEmptyArray = $this->createMockEndpointData([
            'uri' => '/empty-array-response',
            'httpMethods' => ['GET'],
            'responses' => [[
                'status' => 200,
                'content' => '[]',
            ]],
        ]);

        $groups = [$this->createGroup([$endpointWithNullContent, $endpointWithEmptyArray])];
        $results = $this->generate($groups);

        $nullResponseSpec = $results['paths']['/null-response']['get']['responses']['200'];
        $this->assertArrayHasKey('application/json', $nullResponseSpec['content']);
        $schemaForNull = $nullResponseSpec['content']['application/json']['schema'];
        $this->assertEquals('object', $schemaForNull['type']);
        $this->assertTrue($schemaForNull['nullable']);

        $emptyArrayResponseSpec = $results['paths']['/empty-array-response']['get']['responses']['200'];
        $this->assertArrayHasKey('application/json', $emptyArrayResponseSpec['content']);
        $schemaForEmptyArray = $emptyArrayResponseSpec['content']['application/json']['schema'];
        $this->assertEquals('array', $schemaForEmptyArray['type']);
        $this->assertEquals(['type' => 'object'], $schemaForEmptyArray['items']); // Scribe defaults items to 'object' for empty arrays
        $this->assertEquals([], $schemaForEmptyArray['example']);
    }

    /** @test */
    public function addsRequiredFieldsOnArrayOfObjects()
    {
        $endpointData = $this->createMockEndpointData([
            'httpMethods' => ['GEt'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => 200,
                    'description' => 'List of entities',
                    'content' => '{"data":[{"name":"Resource name","uuid":"UUID","primary":true}]}',
                ],
            ],
            'responseFields' => [
                'data' => [
                    'name' => 'data',
                    'type' => 'array',
                    'description' => 'Data wrapper',
                ],
                'data.name' => [
                    'name' => 'Resource name',
                    'type' => 'string',
                    'description' => 'Name of the resource object',
                    'required' => true,
                ],
                'data.uuid' => [
                    'name' => 'Resource UUID',
                    'type' => 'string',
                    'description' => 'Unique ID for the resource',
                    'required' => true,
                ],
                'data.primary' => [
                    'name' => 'Is primary',
                    'type' => 'bool',
                    'description' => 'Is primary resource',
                    'required' => true,
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData])];

        $results = $this->generate($groups);

        $this->assertArraySubset([
            '200' => [
                'description' => 'List of entities',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'type' => 'array',
                                    'description' => 'Data wrapper',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'description' => 'Name of the resource object',
                                            ],
                                            'uuid' => [
                                                'type' => 'string',
                                                'description' => 'Unique ID for the resource',
                                            ],
                                            'primary' => [
                                                'type' => 'boolean',
                                                'description' => 'Is primary resource',
                                            ],
                                        ],
                                    ],
                                    'required' => [
                                        'name',
                                        'uuid',
                                        'primary',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['get']['responses']);
    }

    /** @test */
    public function generatesCorrectlyForArrayOfStrings()
    {
        $endpointData = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => 200,
                    'description' => 'List of entities',
                    'content' => '{"data":["Resource name"]}',
                ],
            ],
            'responseFields' => [
                'data' => [
                    'name' => 'data',
                    'type' => 'string[]',
                    'description' => 'Data wrapper',
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData])];

        $results = $this->generate($groups);

        $this->assertArraySubset([
            '200' => [
                'description' => 'List of entities',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'type' => 'array',
                                    'description' => 'Data wrapper',
                                    'items' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['get']['responses']);
    }

    /** @test */
    public function addsMultipleResponsesCorrectlyUsingOneOf()
    {
        $endpointData1 = $this->createMockEndpointData([
            'httpMethods' => ['POST'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => 201,
                    'description' => 'This one',
                    'content' => '{"this": "one"}',
                ],
                [
                    'status' => 201,
                    'description' => 'No, that one.',
                    'content' => '{"that": "one"}',
                ],
                [
                    'status' => 200,
                    'description' => 'A separate one',
                    'content' => '{"the other": "one"}',
                ],
            ],
        ]);
        $groups = [$this->createGroup([$endpointData1])];

        $results = $this->generate($groups);

        $this->assertArraySubset([
            '200' => [
                'description' => 'A separate one',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'the other' => [
                                    'example' => 'one',
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '201' => [
                'description' => '',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'oneOf' => [
                                [
                                    'type' => 'object',
                                    'description' => 'This one',
                                    'properties' => [
                                        'this' => [
                                            'example' => 'one',
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'object',
                                    'description' => 'No, that one.',
                                    'properties' => [
                                        'that' => [
                                            'example' => 'one',
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['post']['responses']);
    }

    /** @test */
    public function addsMoreThanTwoAnswersCorrectlyUsingOneOf()
    {
        $endpointData1 = $this->createMockEndpointData([
            'httpMethods' => ['POST'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => 201,
                    'description' => 'This one',
                    'content' => '{"this": "one"}',
                ],
                [
                    'status' => 201,
                    'description' => 'No, that one.',
                    'content' => '{"that": "one"}',
                ],
                [
                    'status' => 201,
                    'description' => 'No, another one.',
                    'content' => '{"another": "one"}',
                ],
                [
                    'status' => 200,
                    'description' => 'A separate one',
                    'content' => '{"the other": "one"}',
                ],
            ],
        ]);
        $groups = [$this->createGroup([$endpointData1])];

        $results = $this->generate($groups);

        $this->assertArraySubset([
            '200' => [
                'description' => 'A separate one',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'the other' => [
                                    'example' => 'one',
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '201' => [
                'description' => '',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'oneOf' => [
                                [
                                    'type' => 'object',
                                    'description' => 'This one',
                                    'properties' => [
                                        'this' => [
                                            'example' => 'one',
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'object',
                                    'description' => 'No, that one.',
                                    'properties' => [
                                        'that' => [
                                            'example' => 'one',
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'object',
                                    'description' => 'No, another one.',
                                    'properties' => [
                                        'another' => [
                                            'example' => 'one',
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['post']['responses']);
    }

    /** @test */
    public function addsEnumValuesToResponseProperties()
    {
        $endpointData = $this->createMockEndpointData([
            'httpMethods' => ['GEt'],
            'uri' => '/path1',
            'responses' => [
                [
                    'status' => 200,
                    'description' => 'List of entities',
                    'content' => '{"data":[{"name":"Resource name","uuid":"UUID","primary":true}]}',
                ],
            ],
            'responseFields' => [
                'data' => [
                    'name' => 'data',
                    'type' => 'array',
                    'description' => 'Data wrapper',
                ],
                'data.name' => [
                    'name' => 'Resource name',
                    'type' => 'string',
                    'description' => 'Name of the resource object',
                    'required' => true,
                ],
                'data.uuid' => [
                    'name' => 'Resource UUID',
                    'type' => 'string',
                    'description' => 'Unique ID for the resource',
                    'required' => true,
                ],
                'data.primary' => [
                    'name' => 'Is primary',
                    'type' => 'bool',
                    'description' => 'Is primary resource',
                    'required' => true,
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData])];

        $results = $this->generate($groups);

        $this->assertArraySubset([
            '200' => [
                'description' => 'List of entities',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'type' => 'array',
                                    'description' => 'Data wrapper',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'description' => 'Name of the resource object',
                                            ],
                                            'uuid' => [
                                                'type' => 'string',
                                                'description' => 'Unique ID for the resource',
                                            ],
                                            'primary' => [
                                                'type' => 'boolean',
                                                'description' => 'Is primary resource',
                                            ],
                                        ],
                                    ],
                                    'required' => [
                                        'name',
                                        'uuid',
                                        'primary',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path1']['get']['responses']);
    }

    /** @test */
    public function doesNotAddEmptyEnumArraysWhenResponseFieldHasNoEnumValues()
    {
        $endpointData = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/test',
            'responses' => [
                [
                    'status' => 200,
                    'description' => 'Success response',
                    'content' => '{"status":"active","message":"Hello world"}',
                ],
            ],
            'responseFields' => [
                'status' => [
                    'name' => 'status',
                    'type' => 'string',
                    'required' => true,
                    // enumValues is not set, which means it defaults to an empty array
                ],
                'message' => [
                    'name' => 'message',
                    'type' => 'string',
                    'required' => true,
                    'enumValues' => [], // explicitly empty enum array
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData])];
        $results = $this->generate($groups);

        $responseSchema = $results['paths']['/test']['get']['responses']['200']['content']['application/json']['schema'];
        $statusProperty = $responseSchema['properties']['status'];
        $messageProperty = $responseSchema['properties']['message'];

        // Empty enum array should not be added to the schema
        $this->assertArrayNotHasKey('enum', $statusProperty, 'ResponseField with only required parameter should not have empty enum array');
        $this->assertArrayNotHasKey('enum', $messageProperty, 'ResponseField with empty enumValues should not have empty enum array in schema');

        // Assert that the required fields are correctly set
        $this->assertContains('status', $responseSchema['required']);
        $this->assertContains('message', $responseSchema['required']);
    }

    /** @test */
    public function addsEnumValuesToResponsePropertiesWhenSpecified()
    {
        $endpointData = $this->createMockEndpointData([
            'httpMethods' => ['GET'],
            'uri' => '/test',
            'responses' => [
                [
                    'status' => 200,
                    'description' => 'Success response',
                    'content' => '{"status":"active"}',
                ],
            ],
            'responseFields' => [
                'status' => [
                    'name' => 'status',
                    'type' => 'string',
                    'required' => true,
                    'enumValues' => ['active', 'inactive', 'pending'],
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointData])];
        $results = $this->generate($groups);

        $responseSchema = $results['paths']['/test']['get']['responses']['200']['content']['application/json']['schema'];
        $statusProperty = $responseSchema['properties']['status'];

        // Correct enum values should be added to the schema
        $this->assertArrayHasKey('enum', $statusProperty, 'ResponseField with enumValues should have enum array in schema');
        $this->assertEquals(['active', 'inactive', 'pending'], $statusProperty['enum']);

        // Assert that the required field is correctly set
        $this->assertContains('status', $responseSchema['required']);
    }

    /** @test */
    public function listsRequiredPropertiesInRequestBody()
    {
        $endpointData = $this->createMockEndpointData([
            'uri' => '/path',
            'httpMethods' => ['POST'],
            'bodyParameters' => [
                'my_field' => [
                    'name' => 'my_field',
                    'description' => '',
                    'required' => true,
                    'example' => 'abc',
                    'type' => 'string',
                ],
                'other_field.nested_field' => [
                    'name' => 'nested_field',
                    'description' => '',
                    'required' => true,
                    'example' => 'abc',
                    'type' => 'string',
                ],
            ],
        ]);
        $groups = [$this->createGroup([$endpointData])];
        $results = $this->generate($groups);

        $this->assertArraySubset([
            'requestBody' => [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'my_field' => [
                                    'type' => 'string',
                                ],
                                'other_field' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'nested_field' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                    'required' => ['nested_field'],
                                ],
                            ],
                            'required' => ['my_field'],
                        ],
                    ],
                ],
            ],
        ], $results['paths']['/path']['post']);
    }

    /** @test */
    public function canExtendOpenapiGenerator()
    {
        $endpointData1 = $this->createMockEndpointData([
            'uri' => '/path',
            'httpMethods' => ['POST'],
            'custom' => ['permissions' => ['post:view']],
        ]);
        $groups = [$this->createGroup([$endpointData1])];
        $extraGenerator = TestOpenApiGenerator::class;
        $config = array_merge($this->config, [
            'openapi' => [
                'version' => '3.0.3',
                'generators' => [
                    $extraGenerator,
                ],
            ],
        ]);
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));

        $results = $writer->generateSpecContent($groups);

        $this->assertEquals('3.0.3', $results['openapi']);
        $this->assertEquals([['default' => ['post:view']]], $results['paths']['/path']['post']['security']);
    }

    /** @test */
    public function canExtendOpenapiGeneratorParameters()
    {
        $endpointData1 = $this->createMockEndpointData([
            'uri' => '/{slug}/path',
            'httpMethods' => ['POST'],
            'custom' => ['permissions' => ['post:view']],
            'urlParameters.slug' => [
                'description' => 'Something',
                'required' => true,
                'example' => 56,
                'type' => 'integer',
                'name' => 'slug',
            ],
        ]);
        $groups = [$this->createGroup([$endpointData1])];
        $extraGenerator = ComponentsOpenApiGenerator::class;
        $config = array_merge($this->config, [
            'openapi' => [
                'version' => '3.0.3',
                'generators' => [
                    $extraGenerator,
                ],
            ],
        ]);
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));

        $results = $writer->generateSpecContent($groups);

        $this->assertEquals('3.0.3', $results['openapi']);
        $actualParameters = $results['paths']['/{slug}/path']['parameters'];
        $this->assertCount(1, $actualParameters);
        $this->assertEquals(['$ref' => '#/components/parameters/slugParam'], $actualParameters[0]);
        $this->assertEquals([
            'slugParam' => [
                'in' => 'path',
                'name' => 'slug',
                'description' => 'The slug of the organization.',
                'example' => 'acme-corp',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                ],
            ],
        ], $results['components']['parameters']);
    }

    /** @test */
    public function usesOpenapi31WhenConfigured()
    {
        $config = array_merge($this->config, [
            'openapi' => ['version' => '3.1.0'],
        ]);
        $endpointData = $this->createMockEndpointData();
        $groups = [$this->createGroup([$endpointData])];

        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));
        $results = $writer->generateSpecContent($groups);

        $this->assertEquals('3.1.0', $results['openapi']);
    }

    /** @test */
    public function usesJsonSchemaNullableSyntaxInOpenapi31()
    {
        $config = array_merge($this->config, [
            'openapi' => ['version' => '3.1.0'],
        ]);

        $endpointWithNullableParam = $this->createMockEndpointData([
            'uri' => '/test',
            'httpMethods' => ['POST'],
            'bodyParameters' => [
                'nullable_field' => [
                    'name' => 'nullable_field',
                    'type' => 'string',
                    'required' => false,
                    'description' => 'A nullable field',
                    'example' => 'test',
                    'nullable' => true,
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointWithNullableParam])];
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));
        $results = $writer->generateSpecContent($groups);

        $requestBodySchema = $results['paths']['/test']['post']['requestBody']['content']['application/json']['schema'];
        $nullableFieldSchema = $requestBodySchema['properties']['nullable_field'];

        // In OpenAPI 3.1, nullable fields use JSON Schema's type array syntax
        $this->assertIsArray($nullableFieldSchema['type']);
        $this->assertContains('string', $nullableFieldSchema['type']);
        $this->assertContains('null', $nullableFieldSchema['type']);
        $this->assertArrayNotHasKey('nullable', $nullableFieldSchema);
    }

    /** @test */
    public function usesNullablePropertyInOpenapi30()
    {
        $config = array_merge($this->config, [
            'openapi' => ['version' => '3.0.3'],
        ]);

        $endpointWithNullableParam = $this->createMockEndpointData([
            'uri' => '/test',
            'httpMethods' => ['POST'],
            'bodyParameters' => [
                'nullable_field' => [
                    'name' => 'nullable_field',
                    'type' => 'string',
                    'required' => false,
                    'description' => 'A nullable field',
                    'example' => 'test',
                    'nullable' => true,
                ],
            ],
        ]);

        $groups = [$this->createGroup([$endpointWithNullableParam])];
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));
        $results = $writer->generateSpecContent($groups);

        $requestBodySchema = $results['paths']['/test']['post']['requestBody']['content']['application/json']['schema'];
        $nullableFieldSchema = $requestBodySchema['properties']['nullable_field'];

        // In OpenAPI 3.0, nullable fields use the nullable property
        $this->assertEquals('string', $nullableFieldSchema['type']);
        $this->assertTrue($nullableFieldSchema['nullable']);
    }

    /** @test */
    public function handlesNullResponseContentInOpenapi31()
    {
        $config = array_merge($this->config, [
            'openapi' => ['version' => '3.1.0'],
        ]);

        $endpointWithNullContent = $this->createMockEndpointData([
            'uri' => '/null-response',
            'httpMethods' => ['GET'],
            'responses' => [[
                'status' => 200,
                'content' => null,
            ]],
        ]);

        $groups = [$this->createGroup([$endpointWithNullContent])];
        $writer = new OpenAPISpecWriter(new DocumentationConfig($config));
        $results = $writer->generateSpecContent($groups);

        $nullResponseSpec = $results['paths']['/null-response']['get']['responses']['200'];
        $schemaForNull = $nullResponseSpec['content']['application/json']['schema'];

        // In OpenAPI 3.1, null responses use JSON Schema's type array syntax
        $this->assertIsArray($schemaForNull['type']);
        $this->assertContains('object', $schemaForNull['type']);
        $this->assertContains('null', $schemaForNull['type']);
        $this->assertArrayNotHasKey('nullable', $schemaForNull);
    }

    protected function createMockEndpointData(array $custom = []): OutputEndpointData
    {
        $faker = Factory::create();
        $path = '/' . $faker->word();
        $data = [
            'uri' => $path,
            'httpMethods' => $faker->randomElements(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 1),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'metadata' => [
                'title' => $faker->sentence(),
                'description' => $faker->randomElement([$faker->sentence(), '']),
                'authenticated' => $faker->boolean(),
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

        return OutputEndpointData::create($data);
    }

    protected function createGroup(array $endpoints)
    {
        $faker = Factory::create();

        return [
            'description' => '',
            'name' => $faker->randomElement(['Endpoints', 'Group A', 'Group B']),
            'endpoints' => $endpoints,
        ];
    }

    protected function generate(array $groups): array
    {
        $writer = new OpenAPISpecWriter(new DocumentationConfig($this->config));

        return $writer->generateSpecContent($groups);
    }
}
