<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Scribe;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Globals;

class AfterExtractingHookTest extends BaseLaravelTest
{
    protected array $config = [
        'strategies' => [
            'metadata' => [
                ...Defaults::METADATA_STRATEGIES,
            ],
            'headers' => [
                ...Defaults::HEADERS_STRATEGIES,
            ],
            'urlParameters' => [
                ...Defaults::URL_PARAMETERS_STRATEGIES,
            ],
            'queryParameters' => [
                ...Defaults::QUERY_PARAMETERS_STRATEGIES,
            ],
            'bodyParameters' => [
                ...Defaults::BODY_PARAMETERS_STRATEGIES,
            ],
            'responses' => [],
            'responseFields' => [
                ...Defaults::RESPONSE_FIELDS_STRATEGIES,
            ],
        ],
    ];

    protected function tearDown(): void
    {
        Globals::$__afterExtracting = null;
        parent::tearDown();
    }

    /** @test */
    public function can_use_after_extracting_hook()
    {
        $route = new Route(['GET'], 'api/test', ['uses' => [TestController::class, 'withEndpointDescription']]);

        Scribe::afterExtracting(static function (ExtractedEndpointData $endpointData) {
            $endpointData->metadata->title = 'Modified Title';
            $endpointData->headers['X-Modified-By'] = 'Hook';
        });

        $extractor = new Extractor(new DocumentationConfig($this->config));
        $parsed = $extractor->processRoute($route);

        $this->assertSame('Modified Title', $parsed->metadata->title);
        $this->assertSame('Hook', $parsed->headers['X-Modified-By']);
    }

    /** @test */
    public function after_extracting_hook_is_called_only_once_per_route()
    {
        $route = new Route(['GET'], 'api/test', ['uses' => [TestController::class, 'withEndpointDescription']]);

        $callCount = 0;
        Scribe::afterExtracting(function (ExtractedEndpointData $endpointData) use (&$callCount) {
            $callCount++;
        });

        $extractor = new Extractor(new DocumentationConfig($this->config));
        $extractor->processRoute($route);

        $this->assertSame(1, $callCount);
    }

    /** @test */
    public function after_extracting_hook_can_access_route_middlewares()
    {
        $route = new Route(['GET'], 'api/test', ['uses' => [TestController::class, 'withEndpointDescription']]);
        $route->middleware(['auth:agent_api', 'throttle:60,1']);

        Scribe::afterExtracting(static function (ExtractedEndpointData $endpointData) {
            $middlewares = $endpointData->route->gatherMiddleware();
            if (in_array('auth:agent_api', $middlewares, true)) {
                $endpointData->metadata->description .= 'Requires authentication: `Agent`';
            } elseif (in_array('auth:api', $middlewares, true)) {
                $endpointData->metadata->description .= 'Requires authentication: `User`';
            }
        });

        $extractor = new Extractor(new DocumentationConfig($this->config));
        $parsed = $extractor->processRoute($route);

        $this->assertStringContainsString('Requires authentication: `Agent`', $parsed->metadata->description);
    }
}
