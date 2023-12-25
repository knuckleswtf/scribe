<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Dingo\Api\Routing\Router;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls;
use Knuckles\Scribe\Scribe;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Illuminate\Support\Facades\Route as LaravelRouteFacade;
use Symfony\Component\HttpFoundation\Request;

class ResponseCallsTest extends BaseLaravelTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig(['database_connections_to_transact' => []]);
    }

    /** @test */
    public function can_call_route_and_fetch_response()
    {
        $route = LaravelRouteFacade::post('/shouldFetchRouteResponse', [TestController::class, 'shouldFetchRouteResponse']);

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy(ExtractedEndpointData::fromRoute($route), $this->convertRules($rules));

        $this->assertEquals(200, $results[0]['status']);
        $this->assertArraySubset([
            'id' => 4,
            'name' => 'banana',
            'color' => 'red',
            'weight' => '1 kg',
            'delicious' => true,
        ], json_decode($results[0]['content'], true));
    }

    /** @test */
    public function can_upload_file_parameters_in_response_calls()
    {
        $route = RouteFacade::post('/withFormDataParams', [TestController::class, 'withFormDataParams']);

        $this->setConfig(['routes.0.apply.response_calls.methods' => ['POST']]);
        $parsed = (new Extractor())->processRoute($route, config('scribe.routes.0.apply'));

        $responses = $parsed->responses->toArray();
        $this->assertCount(1, $responses);
        $this->assertArraySubset([
            "status" => 200,
            "description" => null,
            "content" => '{"filename":"scribe.php","filepath":"config","name":"cat.jpg"}',
        ], $responses[0]);
    }

    /** @test */
    public function uses_configured_settings_when_calling_route()
    {
        $route = LaravelRouteFacade::post('/echo/{id}', [TestController::class, 'echoesRequestValues']);

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'queryParams' => [
                    'queryParam' => 'queryValue',
                ],
                'bodyParams' => [
                    'bodyParam' => 'bodyValue',
                ],
            ],
        ];

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'auth' => ['headers', 'Authorization', 'Bearer bearerToken'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'header' => 'headerValue',
            ],
        ]);

        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy($endpointData,
            $this->convertRules($rules));

        $this->assertEquals(200, $results[0]['status']);

        $responseContent = json_decode($results[0]['content'], true);
        $this->assertEquals('queryValue', $responseContent['queryParam']);
        $this->assertEquals('bodyValue', $responseContent['bodyParam']);
        $this->assertEquals('headerValue', $responseContent['header']);
        $this->assertEquals('Bearer bearerToken', $responseContent['auth']);
    }

    /** @test */
    public function can_override_application_config_during_response_call()
    {
        $route = LaravelRouteFacade::post('/echoesConfig', [TestController::class, 'echoesConfig']);

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy(ExtractedEndpointData::fromRoute($route), $this->convertRules($rules));
        $originalValue = json_decode($results[0]['content'], true)['app.env'];

        $now = time();
        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'config' => [
                    'app.env' => $now,
                ],
            ],
        ];

        $results = $strategy(ExtractedEndpointData::fromRoute($route), $this->convertRules($rules));
        $newValue = json_decode($results[0]['content'], true)['app.env'];
        $this->assertEquals($now, $newValue);
        $this->assertNotEquals($originalValue, $newValue);
    }

    /** @test */
    public function calls_beforeResponseCall_hook()
    {
        Scribe::beforeResponseCall(function (Request $request, ExtractedEndpointData $endpointData) {
            $request->headers->set("header", "overridden_".$request->headers->get("header"));
            $request->headers->set("Authorization", "overridden_".$request->headers->get("Authorization"));
            $request->query->set("queryParam", "overridden_".$request->query->get("queryParam"));
            $request->request->set("bodyParam", "overridden_".$endpointData->uri.$request->request->get("bodyParam"));
        });

        $route = LaravelRouteFacade::post('/echo/{id}', [TestController::class, 'echoesRequestValues']);

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'queryParams' => [
                    'queryParam' => 'queryValue',
                ],
                'bodyParams' => [
                    'bodyParam' => 'bodyValue',
                ],
            ],
        ];

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'auth' => ['headers', 'Authorization', 'Bearer bearerToken'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'header' => 'headerValue',
            ],
        ]);

        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy($endpointData, $this->convertRules($rules));

        $this->assertEquals(200, $results[0]['status']);

        $responseContent = json_decode($results[0]['content'], true);
        $this->assertEquals('overridden_queryValue', $responseContent['queryParam']);
        $this->assertEquals('overridden_headerValue', $responseContent['header']);
        $this->assertEquals('overridden_Bearer bearerToken', $responseContent['auth']);
        $this->assertEquals('overridden_echo/{id}bodyValue', $responseContent['bodyParam']);

        Scribe::beforeResponseCall(fn() => null);
    }

    /**
     * @test
     * @group dingo
     */
    public function can_call_route_and_fetch_response_with_dingo()
    {
        $route = $this->registerDingoRoute('post', '/shouldFetchRouteResponse', 'shouldFetchRouteResponse');

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $strategy = new ResponseCalls(new DocumentationConfig());
        $results = $strategy(ExtractedEndpointData::fromRoute($route), $this->convertRules($rules));

        $this->assertEquals(200, $results[0]['status']);
        $this->assertArraySubset([
            'id' => 4,
            'name' => 'banana',
            'color' => 'red',
            'weight' => '1 kg',
            'delicious' => true,
        ], json_decode($results[0]['content'], true));
    }

    /**
     * @test
     * @group dingo
     */
    public function uses_configured_settings_when_calling_route_with_dingo()
    {
        $route = $this->registerDingoRoute('post', '/echo/{id}', 'echoesRequestValues');

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'queryParams' => [
                    'queryParam' => 'queryValue',
                ],
                'bodyParams' => [
                    'bodyParam' => 'bodyValue',
                ],
            ],
        ];

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'header' => 'headerValue',
            ],
        ]);
        $strategy = new ResponseCalls(new DocumentationConfig());
        $results = $strategy($endpointData, $this->convertRules($rules));

        $this->assertEquals(200, $results[0]['status']);

        $responseContent = json_decode($results[0]['content'], true);
        $this->assertEquals('queryValue', $responseContent['queryParam']);
        $this->assertEquals('bodyValue', $responseContent['bodyParam']);
        $this->assertEquals('headerValue', $responseContent['header']);
    }

    /**
     * @test
     * @group dingo
     */
    public function can_override_application_config_during_response_call_with_dingo()
    {
        $route = $this->registerDingoRoute('post', '/echoesConfig', 'echoesConfig');

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $strategy = new ResponseCalls(new DocumentationConfig());
        $results = $strategy(ExtractedEndpointData::fromRoute($route), $this->convertRules($rules));
        $originalValue = json_decode($results[0]['content'], true)['app.env'];

        $now = time();
        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'config' => [
                    'app.env' => $now,
                ],
            ],
        ];

        $results = $strategy(ExtractedEndpointData::fromRoute($route), $this->convertRules($rules));
        $newValue = json_decode($results[0]['content'], true)['app.env'];
        $this->assertEquals($now, $newValue);
        $this->assertNotEquals($originalValue, $newValue);
    }

    /** @test */
    public function does_not_make_response_call_if_success_response_already_gotten()
    {
        $route = LaravelRouteFacade::post('/shouldFetchRouteResponse', [TestController::class, 'shouldFetchRouteResponse']);

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'responses' => new ResponseCollection([
                [
                    'status' => 200,
                    'content' => json_encode(['message' => 'LOL']),
                ],
            ]),
        ]);
        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy($endpointData, $this->convertRules($rules));

        $this->assertNull($results);
    }

    public function registerDingoRoute(string $httpMethod, string $path, string $controllerMethod)
    {
        $desiredRoute = null;
        /** @var Router $api */
        $api = app(Router::class);
        $api->version('v1', function (Router $api) use ($controllerMethod, $path, $httpMethod, &$desiredRoute) {
            $desiredRoute = $api->$httpMethod($path, [TestController::class, $controllerMethod]);
        });
        $routes = app(\Dingo\Api\Routing\Router::class)->getRoutes('v1');

        /*
         * Doing this bc we want an instance of Dingo\Api\Routing\Route, not Illuminate\Routing\Route, which the method above returns
         */
        return collect($routes)
            ->first(function (Route $route) use ($desiredRoute) {
                return $route->uri() === $desiredRoute->uri();
            });
    }

    protected function convertRules(array $rules): mixed
    {
        return Extractor::transformOldRouteRulesIntoNewSettings('responses', $rules, ResponseCalls::class);
    }
}
