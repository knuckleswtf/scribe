<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Tests\BaseUnitTest;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\PostmanCollectionWriter;

/**
 * @internal
 *
 * @coversNothing
 */
class PostmanCollectionWriterTest extends BaseUnitTest
{
    public function test_correct_structure_is_followed()
    {
        $config = ['title' => 'Test API', 'description' => 'A fake description', 'base_url' => 'http://localhost'];

        $collection = $this->generate($config);

        $this->assertSame('Test API', $collection['info']['name']);
        $this->assertSame('A fake description', $collection['info']['description']);
    }

    public function test_endpoint_is_parsed()
    {
        $endpointData = $this->createMockEndpointData('some/path');

        // Ensure method is set correctly for assertion later
        $endpointData->httpMethods = ['GET'];

        $endpoints = $this->createMockEndpointGroup([$endpointData], 'Group');
        $collection = $this->generate(endpoints: [$endpoints]);

        $this->assertSame('Group', data_get($collection, 'item.0.name'), 'Group name exists');

        $item = data_get($collection, 'item.0.item.0');
        $this->assertSame('GET some/path', $item['name'], 'Name defaults to path');
        $this->assertSame('fake.localhost', data_get($collection, 'variable.0.value'));
        $this->assertSame('{{baseUrl}}', data_get($item, 'request.url.host'));
        $this->assertSame('some/path', data_get($item, 'request.url.path'), 'Path is set correctly');
        $this->assertEmpty(data_get($item, 'request.url.query'), 'Query parameters are empty');
        $this->assertSame('GET', data_get($item, 'request.method'), 'Method is correctly resolved');
        $this->assertContains([
            'key' => 'Accept',
            'value' => 'application/json',
        ], data_get($item, 'request.header'), 'JSON Accept header is added');
    }

    public function test_headers_are_pulled_from_route()
    {
        $endpointData = $this->createMockEndpointData('some/path');
        $endpointData->headers = ['X-Fake' => 'Test'];

        $endpoints = $this->createMockEndpointGroup([$endpointData]);
        $collection = $this->generate(endpoints: [$endpoints]);

        $this->assertContains([
            'key' => 'X-Fake',
            'value' => 'Test',
        ], data_get($collection, 'item.0.item.0.request.header'));
    }

    public function test_url_parameters_are_represented_properly()
    {
        $endpointData = $this->createMockEndpointData('fake/{param}');
        $endpointData->urlParameters['param'] = new Parameter([
            'name' => 'param',
            'description' => 'A test description for the test param',
            'required' => true,
            'example' => 'foobar',
        ]);

        $endpoints = $this->createMockEndpointGroup([$endpointData]);
        $collection = $this->generate(endpoints: [$endpoints]);

        $item = data_get($collection, 'item.0.item.0');
        $this->assertSame('POST fake/{param}', $item['name'], 'Name defaults to URL path');
        $this->assertSame('fake/:param', data_get($item, 'request.url.path'), 'Path is converted');

        $variableData = data_get($collection, 'item.0.item.0.request.url.variable');
        $this->assertCount(1, $variableData);
        $this->assertEquals([
            'id' => 'param',
            'key' => 'param',
            'value' => 'foobar',
            'description' => 'A test description for the test param',
        ], $variableData[0]);
    }

    public function test_query_parameters_are_documented()
    {
        $endpointData = $this->createMockEndpointData('fake/path');

        $endpointData->queryParameters = [
            'limit' => new Parameter([
                'name' => 'limit',
                'type' => 'integer',
                'description' => 'A fake limit for my fake endpoint',
                'required' => true,
                'example' => 5,
            ]),
            'filters' => new Parameter([
                'name' => 'filters',
                'type' => 'integer[]',
                'description' => 'Filters',
                'required' => true,
                'example' => [34, 12],
            ]),
        ];
        $endpointData->cleanQueryParameters = Extractor::cleanParams($endpointData->queryParameters);

        $endpoints = $this->createMockEndpointGroup([$endpointData]);
        $collection = $this->generate(endpoints: [$endpoints]);

        $variableData = data_get($collection, 'item.0.item.0.request.url.query');

        $this->assertCount(3, $variableData);
        $this->assertEquals([
            'key' => 'limit',
            'value' => '5',
            'description' => 'A fake limit for my fake endpoint',
            'disabled' => false,
        ], $variableData[0]);
        $this->assertEquals([
            'key' => 'filters[0]',
            'value' => '34',
            'description' => 'Filters',
            'disabled' => false,
        ], $variableData[1]);
        $this->assertEquals([
            'key' => 'filters[1]',
            'value' => '12',
            'description' => 'Filters',
            'disabled' => false,
        ], $variableData[2]);
    }

    public function test_url_parameters_are_not_included_if_missing_from_path()
    {
        $endpointData = $this->createMockEndpointData('fake/path');

        $endpointData->urlParameters['limit'] = new Parameter([
            'name' => 'limit',
            'description' => 'A fake limit for my fake endpoint',
            'required' => false,
            'example' => 5,
        ]);

        $endpoints = $this->createMockEndpointGroup([$endpointData]);
        $collection = $this->generate(endpoints: [$endpoints]);

        $variableData = data_get($collection, 'item.0.item.0.request.url.query');

        $this->assertCount(0, $variableData);
    }

    public function test_query_parameters_are_disabled_with_no_value_when_not_required()
    {
        $endpointData = $this->createMockEndpointData('fake/path');
        $endpointData->queryParameters = [
            'required' => new Parameter([
                'name' => 'required',
                'type' => 'string',
                'description' => 'A required param with a null value',
                'required' => true,
                'example' => null,
            ]),
            'not_required' => new Parameter([
                'name' => 'not_required',
                'type' => 'string',
                'description' => 'A not required param with a null value',
                'required' => false,
                'example' => null,
            ]),
        ];
        $endpointData->cleanQueryParameters = Extractor::cleanParams($endpointData->queryParameters);

        $endpoints = $this->createMockEndpointGroup([$endpointData]);
        $collection = $this->generate(endpoints: [$endpoints]);

        $variableData = data_get($collection, 'item.0.item.0.request.url.query');

        $this->assertCount(2, $variableData);
        $this->assertContains([
            'key' => 'required',
            'value' => '',
            'description' => 'A required param with a null value',
            'disabled' => false,
        ], $variableData);
        $this->assertContains([
            'key' => 'not_required',
            'value' => '',
            'description' => 'A not required param with a null value',
            'disabled' => true,
        ], $variableData);
    }

    public function test_query_parameter_keys_and_values_are_not_pre_url_encoded()
    {
        // Per Postman Collection v2.1 spec, query[].key/value are stored as raw strings
        // and the Postman client encodes them when sending. Pre-encoding here causes
        // a double-encode on send (e.g. `,` → `%2C` → `%252C` at the server).
        $endpointData = $this->createMockEndpointData('fake/{id}');
        $endpointData->urlParameters['id'] = new Parameter([
            'name' => 'id',
            'description' => '',
            'required' => true,
            'example' => 'foo bar',
        ]);
        $endpointData->queryParameters = [
            'include' => new Parameter([
                'name' => 'include',
                'type' => 'string',
                'description' => 'Comma-separated relationships',
                'required' => false,
                'example' => 'customer,reward',
            ]),
            'filter[email]' => new Parameter([
                'name' => 'filter[email]',
                'type' => 'string',
                'description' => 'Filter',
                'required' => false,
                'example' => 'test@example.com',
            ]),
        ];
        $endpointData->cleanQueryParameters = Extractor::cleanParams($endpointData->queryParameters);

        $endpoints = $this->createMockEndpointGroup([$endpointData]);
        $collection = $this->generate(endpoints: [$endpoints]);

        $url = data_get($collection, 'item.0.item.0.request.url');

        // Query objects keep raw values — Postman encodes on send.
        $this->assertContains([
            'key' => 'include',
            'value' => 'customer,reward',
            'description' => 'Comma-separated relationships',
            'disabled' => false,
        ], $url['query']);
        $this->assertContains([
            'key' => 'filter[email]',
            'value' => 'test@example.com',
            'description' => 'Filter',
            'disabled' => false,
        ], $url['query']);

        // URL variables also stored raw.
        $this->assertSame('foo bar', $url['variable'][0]['value']);

        // Raw URL is encoded for HTTP validity.
        $this->assertStringContainsString('include=customer%2Creward', $url['raw']);
        $this->assertStringContainsString('filter%5Bemail%5D=test%40example.com', $url['raw']);
    }

    public function test_auth_info_is_added_correctly()
    {
        $endpointData1 = $this->createMockEndpointData('some/path');
        $endpointData1->metadata->authenticated = true;
        $endpointData2 = $this->createMockEndpointData('some/other/path');
        $endpoints = $this->createMockEndpointGroup([$endpointData1, $endpointData2], 'Group');

        $config = [
            'title' => 'Test API',
            'base_url' => 'fake.localhost',
            'auth' => [
                'enabled' => true,
                'default' => false,
            ],
        ];
        $config['auth']['in'] = 'bearer';
        $collection = $this->generate($config, [$endpoints]);

        $expected = [
            'type' => 'bearer',
            'bearer' => [
                [
                    'key' => null,
                    'type' => 'string',
                ],
            ],
        ];

        $this->assertEquals($expected, $collection['auth']);
        $this->assertArrayNotHasKey('auth', $collection['item'][0]['item'][0]['request']);
        $this->assertEquals(['type' => 'noauth'], $collection['item'][0]['item'][1]['request']['auth']);

        $config['auth']['in'] = 'query';
        $config['auth']['name'] = 'tokennnn';
        $collection = $this->generate($config, [$endpoints]);

        $this->assertEquals([
            'type' => 'apikey',
            'apikey' => [
                [
                    'key' => 'in',
                    'value' => 'query',
                    'type' => 'string',
                ],
                [
                    'key' => 'key',
                    'value' => 'tokennnn',
                    'type' => 'string',
                ],
            ],
        ], $collection['auth']);
        $this->assertArrayNotHasKey('auth', $collection['item'][0]['item'][0]['request']);
        $this->assertEquals(['type' => 'noauth'], $collection['item'][0]['item'][1]['request']['auth']);
    }

    public function test_organizes_groups_and_subgroups_correctly()
    {
        $endpointData1 = $this->createMockEndpointData('endpoint1');
        $endpointData1->metadata->subgroup = 'Subgroup A';
        $endpointData2 = $this->createMockEndpointData('endpoint2');
        $endpointData3 = $this->createMockEndpointData('endpoint3');
        $endpointData3->metadata->subgroup = 'Subgroup A';
        $endpointData3->metadata->subgroupDescription = 'Subgroup A description';
        $endpoints = $this->createMockEndpointGroup([$endpointData1, $endpointData2, $endpointData3], 'Group A');

        $config = [
            'title' => 'Test API',
            'base_url' => 'fake.localhost',
            'auth' => ['enabled' => false],
        ];
        $collection = $this->generate($config, [$endpoints]);

        $this->assertEquals('Group A', $collection['item'][0]['name']);
        $this->assertEquals(['Subgroup A', 'POST endpoint2'], array_map(fn ($i) => $i['name'], $collection['item'][0]['item']));
        $this->assertEquals(['POST endpoint1', 'POST endpoint3'], array_map(fn ($i) => $i['name'], $collection['item'][0]['item'][0]['item']));
        $this->assertEquals('Subgroup A description', $collection['item'][0]['item'][0]['description']);
    }

    protected function createMockEndpointData(string $path, string $title = ''): OutputEndpointData
    {
        return OutputEndpointData::create([
            'uri' => $path,
            'httpMethods' => ['POST'],
            'metadata' => [
                'title' => $title,
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
        ]);
    }

    protected function createMockEndpointGroup(array $endpoints, string $groupName = 'Group')
    {
        return [
            'description' => '',
            'name' => $groupName,
            'endpoints' => $endpoints,
        ];
    }

    protected function generate(
        array $config = ['base_url' => 'fake.localhost', 'title' => 'Test API'],
        array $endpoints = [],
    ): array {
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));

        return $writer->generatePostmanCollection($endpoints);
    }
}
