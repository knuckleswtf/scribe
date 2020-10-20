<?php

namespace Knuckles\Scribe\Tests\Unit;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Support\Collection;
use Knuckles\Scribe\Extracting\Generator;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\PostmanCollectionWriter;
use PHPUnit\Framework\TestCase;

class PostmanCollectionWriterTest extends TestCase
{
    use ArraySubsetAsserts;

    public function testCorrectStructureIsFollowed()
    {
        $config = ['title' => 'Test API', 'description' => 'A fake description', 'base_url' => 'http://localhost'];

        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection(new Collection());

        $this->assertSame('Test API', $collection['info']['name']);
        $this->assertSame('A fake description', $collection['info']['description']);
    }

    public function testEndpointIsParsed()
    {
        $route = $this->createMockRouteData('some/path');

        // Ensure method is set correctly for assertion later
        $route['methods'] = ['GET'];

        $endpoints = $this->createMockRouteGroup([$route], 'Group');

        $config = ['base_url' => 'fake.localhost', 'title' => 'Test API'];
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection($endpoints);

        $this->assertSame('Group', data_get($collection, 'item.0.name'), 'Group name exists');

        $item = data_get($collection, 'item.0.item.0');
        $this->assertSame('some/path', $item['name'], 'Name defaults to path');
        $this->assertSame('http', data_get($item, 'request.url.protocol'), 'Protocol defaults to http');
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

    public function testHeadersArePulledFromRoute()
    {
        $route = $this->createMockRouteData('some/path');

        $route['headers'] = ['X-Fake' => 'Test'];

        $endpoints = $this->createMockRouteGroup([$route], 'Group');
        $config = ['base_url' => 'fake.localhost', 'title' => 'Test API'];
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection($endpoints);

        $this->assertContains([
            'key' => 'X-Fake',
            'value' => 'Test',
        ], data_get($collection, 'item.0.item.0.request.header'));
    }

    /** @test */
    public function url_parameters_are_represented_properly()
    {
        $fakeRoute = $this->createMockRouteData('fake/{param}');
        $fakeRoute['urlParameters'] = ['param' => [
            'description' => 'A test description for the test param',
            'required' => true,
            'value' => 'foobar',
        ]];
        $endpoints = $this->createMockRouteGroup([$fakeRoute]);

        $config = ['base_url' => 'fake.localhost', 'title' => 'Test API'];
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection($endpoints);

        $item = data_get($collection, 'item.0.item.0');
        $this->assertSame('fake/{param}', $item['name'], 'Name defaults to URL path');
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

    /** @test */
    public function query_parameters_are_documented()
    {
        $fakeRoute = $this->createMockRouteData('fake/path');

        $fakeRoute['queryParameters'] = [
            'limit' => [
                'type' => 'integer',
                'description' => 'A fake limit for my fake endpoint',
                'required' => true,
                'value' => 5,
            ],
            'filters' => [
                'type' => 'integer[]',
                'description' => 'Filters',
                'required' => true,
                'value' => [34, 12],
            ],
        ];
        $fakeRoute['cleanQueryParameters'] = Generator::cleanParams($fakeRoute['queryParameters']);

        $endpoints = $this->createMockRouteGroup([$fakeRoute]);
        $config = ['base_url' => 'fake.localhost', 'title' => 'Test API'];
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection($endpoints);

        $variableData = data_get($collection, 'item.0.item.0.request.url.query');

        $this->assertCount(3, $variableData);
        $this->assertEquals([
            'key' => 'limit',
            'value' => '5',
            'description' => 'A fake limit for my fake endpoint',
            'disabled' => false,
        ], $variableData[0]);
        $this->assertEquals([
            'key' => urlencode('filters[0]'),
            'value' => '34',
            'description' => 'Filters',
            'disabled' => false,
        ], $variableData[1]);
        $this->assertEquals([
            'key' => urlencode('filters[1]'),
            'value' => '12',
            'description' => 'Filters',
            'disabled' => false,
        ], $variableData[2]);
    }

    public function testUrlParametersAreNotIncludedIfMissingFromPath()
    {
        $fakeRoute = $this->createMockRouteData('fake/path');

        $fakeRoute['urlParameters'] = ['limit' => [
            'description' => 'A fake limit for my fake endpoint',
            'required' => false,
            'value' => 5,
        ]];

        $endpoints = $this->createMockRouteGroup([$fakeRoute]);
        $config = ['base_url' => 'fake.localhost', 'title' => 'Test API'];
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection($endpoints);

        $variableData = data_get($collection, 'item.0.item.0.request.url.query');

        $this->assertCount(0, $variableData);
    }

    /** @test */
    public function query_parameters_are_disabled_with_no_value_when_notRequired()
    {
        $fakeRoute = $this->createMockRouteData('fake/path');
        $fakeRoute['queryParameters'] = [
            'required' => [
                'type' => 'string',
                'description' => 'A required param with a null value',
                'required' => true,
                'value' => null,
            ],
            'not_required' => [
                'type' => 'string',
                'description' => 'A not required param with a null value',
                'required' => false,
                'value' => null,
            ],
        ];
        $fakeRoute['cleanQueryParameters'] = Generator::cleanParams($fakeRoute['queryParameters']);

        $endpoints = $this->createMockRouteGroup([$fakeRoute]);
        $config = ['base_url' => 'fake.localhost', 'title' => 'Test API'];
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection($endpoints);

        $variableData = data_get($collection, 'item.0.item.0.request.url.query');

        $this->assertCount(2, $variableData);
        $this->assertContains([
            'key' => 'required',
            'value' => null,
            'description' => 'A required param with a null value',
            'disabled' => false,
        ], $variableData);
        $this->assertContains([
            'key' => 'not_required',
            'value' => null,
            'description' => 'A not required param with a null value',
            'disabled' => true,
        ], $variableData);
    }

    /**
     * @test
     */
    public function auth_info_is_added_correctly()
    {

        $route1 = $this->createMockRouteData('some/path');
        $route1['metadata']['authenticated'] = true;
        $route2 = $this->createMockRouteData('some/other/path');
        $endpoints = $this->createMockRouteGroup([$route1, $route2], 'Group');

        $config = [
            'title' => 'Test API',
            'base_url' => 'fake.localhost',
            'auth' => [
                'enabled' => true,
                'default' => false,
            ]
        ];
        $config['auth']['in'] = 'bearer';
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection($endpoints);

        $this->assertEquals(['type' => 'bearer'], $collection['auth']);
        $this->assertArrayNotHasKey('auth', $collection['item'][0]['item'][0]['request']);
        $this->assertEquals(['type' => 'noauth'], $collection['item'][0]['item'][1]['request']['auth']);

        $config['auth']['in'] = 'query';
        $config['auth']['name'] = 'tokennnn';
        $writer = new PostmanCollectionWriter(new DocumentationConfig($config));
        $collection = $writer->generatePostmanCollection($endpoints);

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
            ]
        ], $collection['auth']);
        $this->assertArrayNotHasKey('auth', $collection['item'][0]['item'][0]['request']);
        $this->assertEquals(['type' => 'noauth'], $collection['item'][0]['item'][1]['request']['auth']);
    }

    protected function createMockRouteData($path, $title = '')
    {
        return [
            'uri' => $path,
            'methods' => ['GET'],
            'headers' => [],
            'metadata' => [
                'groupDescription' => '',
                'title' => $title,
            ],
            'urlParameters' => [],
            'cleanUrlParameters' => [],
            'queryParameters' => [],
            'cleanQueryParameters' => [],
            'bodyParameters' => [],
            'cleanBodyParameters' => [],
            'fileParameters' => [],
            'responses' => [],
            'responseFields' => [],
        ];
    }

    protected function createMockRouteGroup(array $routes, $groupName = 'Group')
    {
        return collect([$groupName => collect($routes)]);
    }
}
