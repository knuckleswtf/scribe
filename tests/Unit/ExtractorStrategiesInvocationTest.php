<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\BaseUnitTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;

class ExtractorStrategiesInvocationTest extends BaseUnitTest
{
    protected ?Extractor $generator;

    protected function tearDown(): void
    {
        EmptyStrategy1::$called = false;
        EmptyStrategy2::$called = false;
        NotDummyMetadataStrategy::$called = false;

        parent::tearDown();
    }

    /** @test */
    public function only_specified_strategies_are_loaded()
    {
        $config = [
            'strategies' => [
                'metadata' => [NotDummyMetadataStrategy::class],
                'bodyParameters' => [
                    EmptyStrategy1::class,
                ],
            ],
        ];
        $this->processRoute($config);

        $this->assertTrue(EmptyStrategy1::$called);
        $this->assertTrue(NotDummyMetadataStrategy::$called);
        $this->assertFalse(EmptyStrategy2::$called);
    }

    /** @test */
    public function supports_override_tuples()
    {
        $config = [
            'strategies' => [
                'headers' => [
                    DummyHeaderStrategy::class,
                    [
                        'override',
                        ['Content-Type' => 'application/xml'],
                    ]
                ],
                'bodyParameters' => [],
            ],
        ];

        $endpointData = $this->processRoute($config);

        $this->assertEquals([
            'Accept' => 'application/form-data',
            'Content-Type' => 'application/xml',
        ], $endpointData->headers);
    }


    /** @test */
    public function supports_strategy_settings_tuples()
    {
        $config = [
            'strategies' => [
                'headers' => [
                    [
                        DummyHeaderStrategy::class,
                        ['use_this_content_type' => 'text/plain'],
                    ]
                ],
                'bodyParameters' => [],
            ],
        ];

        $endpointData = $this->processRoute($config);

        $this->assertEquals([
            'Accept' => 'application/form-data',
            'Content-Type' => 'text/plain',
        ], $endpointData->headers);
    }

    /** @test */
    public function respects_strategy_s_only_setting()
    {
        $config = [
            'strategies' => [
                'bodyParameters' => [
                    [EmptyStrategy1::class, ['only' => 'GET /test']]
                ],
            ],
        ];
        $this->processRoute($config);
        $this->assertFalse(EmptyStrategy1::$called);

        $config['strategies']['bodyParameters'][0] =
            [EmptyStrategy1::class, ['only' => ['GET api/*']]];
        $this->processRoute($config);
        $this->assertTrue(EmptyStrategy1::$called);
    }

    /** @test */
    public function respects_strategy_s_except_setting()
    {
        $config = [
            'strategies' => [
                'bodyParameters' => [
                    [EmptyStrategy1::class, ['except' => 'GET /api*']]
                ],
            ],
        ];
        $this->processRoute($config);
        $this->assertFalse(EmptyStrategy1::$called);

        $config['strategies']['bodyParameters'][0] =
            [EmptyStrategy1::class, ['except' => ['*']]];
        $this->processRoute($config);
        $this->assertFalse(EmptyStrategy1::$called);

        $config['strategies']['bodyParameters'][0] =
            [EmptyStrategy1::class, ['except' => []]];
        $this->processRoute($config);
        $this->assertTrue(EmptyStrategy1::$called);
    }

    /** @test */
    public function responses_from_different_strategies_get_added()
    {
        $config = [
            'strategies' => [
                'bodyParameters' => [],
                'responses' => [DummyResponseStrategy200::class, DummyResponseStrategy400::class],
            ],
        ];
        $parsed = $this->processRoute($config);

        $this->assertCount(2, $parsed->responses->toArray());
        $responses = $parsed->responses->toArray();
        $first = array_shift($responses);
        $this->assertTrue(is_array($first));
        $this->assertEquals(200, $first['status']);
        $this->assertEquals('dummy', $first['content']);

        $second = array_shift($responses);
        $this->assertTrue(is_array($second));
        $this->assertEquals(400, $second['status']);
        $this->assertEquals('dummy2', $second['content']);
    }

    /**
     * @test
     * This is a generalized test, as opposed to the one above for responses only
     */
    public function combines_results_from_different_strategies_in_same_stage()
    {
        $config = [
            'strategies' => [
                'metadata' => [PartialDummyMetadataStrategy1::class, PartialDummyMetadataStrategy2::class],
            ],
        ];
        $parsed = $this->processRoute($config);

        $expectedMetadata = [
            'groupName' => 'dummy',
            'groupDescription' => 'dummy',
            'title' => 'dummy',
            'description' => 'dummy',
            'authenticated' => false,
        ];
        $this->assertArraySubset($expectedMetadata, $parsed->metadata->toArray());
    }

    /** @test */
    public function missing_metadata_is_filled_in()
    {
        $config = [
            'strategies' => [
                'metadata' => [PartialDummyMetadataStrategy2::class],
            ],
        ];
        $parsed = $this->processRoute($config);

        $expectedMetadata = [
            'groupName' => '',
            'groupDescription' => 'dummy',
            'title' => '',
            'description' => 'dummy',
            'authenticated' => false,
        ];
        $this->assertArraySubset($expectedMetadata, $parsed->metadata->toArray());
    }

    public function responsesToSort(): array
    {
        return [
            '400, 200, 201' => [[DummyResponseStrategy400::class, DummyResponseStrategy200::class, DummyResponseStrategy201::class]],
            '201, 400, 200' => [[DummyResponseStrategy201::class, DummyResponseStrategy400::class, DummyResponseStrategy200::class]],
            '400, 201, 200' => [[DummyResponseStrategy400::class, DummyResponseStrategy201::class, DummyResponseStrategy200::class]],
        ];
    }

    /**
     * @test
     * @dataProvider responsesToSort
     */
    public function sort_responses_by_status_code(array $responses)
    {
        $config = [
            'strategies' => [
                'bodyParameters' => [],
                'responses' => $responses,
            ],
        ];
        $parsed = $this->processRoute($config);

        [$first, $second, $third] = $parsed->responses;

        self::assertEquals(200, $first->status);
        self::assertEquals(201, $second->status);
        self::assertEquals(400, $third->status);
    }

    /** @test */
    public function overwrites_metadata_from_previous_strategies_in_same_stage()
    {
        $config = [
            'strategies' => [
                'metadata' => [NotDummyMetadataStrategy::class, PartialDummyMetadataStrategy1::class],
                'bodyParameters' => [],
                'responses' => [],
            ],
        ];
        $parsed = $this->processRoute($config);

        $expectedMetadata = [
            'groupName' => 'dummy',
            'groupDescription' => 'notdummy',
            'title' => 'dummy',
            'description' => 'dummy',
            'authenticated' => false,
        ];
        $this->assertArraySubset($expectedMetadata, $parsed->metadata->toArray());
    }

    protected function processRoute(
        array $config, $routeMethod = "GET", $routePath = "/api/test", $routeName = "dummy"
    ): ExtractedEndpointData
    {
        $route = $this->createRoute($routeMethod, $routePath, $routeName);
        $extractor = new Extractor(new DocumentationConfig($config));
        return $extractor->processRoute($route);
    }

    public function createRoute(string $httpMethod, string $path, string $controllerMethod, $class = TestController::class)
    {
        return new Route([$httpMethod], $path, ['uses' => [$class, $controllerMethod]]);
    }
}


class EmptyStrategy1 extends Strategy
{
    public static $called = false;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        static::$called = true;
        return [];
    }
}

class EmptyStrategy2 extends Strategy
{
    public static $called = false;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        static::$called = true;
        return [];
    }
}

class DummyHeaderStrategy extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        return [
            'Accept' => 'application/form-data',
            'Content-Type' => $settings['use_this_content_type'] ?? 'application/form-data',
        ];
    }
}

class NotDummyMetadataStrategy extends Strategy
{
    public static $called = false;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        static::$called = true;
        return [
            'groupName' => 'notdummy',
            'groupDescription' => 'notdummy',
            'title' => 'notdummy',
            'description' => 'notdummy',
            'authenticated' => true,
        ];
    }
}

class PartialDummyMetadataStrategy1 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [
            'groupName' => 'dummy',
            'title' => 'dummy',
            'description' => 'dummy',
            'authenticated' => false,
        ];
    }
}

class PartialDummyMetadataStrategy2 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [
            'description' => 'dummy',
            'groupDescription' => 'dummy',
        ];
    }
}

class DummyResponseStrategy200 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [['status' => 200, 'content' => 'dummy']];
    }
}

class DummyResponseStrategy201 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [['status' => 201, 'content' => 'dummy2']];
    }
}

class DummyResponseStrategy400 extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return [['status' => 400, 'content' => 'dummy2']];
    }
}
