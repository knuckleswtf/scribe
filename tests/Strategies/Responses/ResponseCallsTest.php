<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as LaravelRouteFacade;
use Illuminate\Support\Facades\Route as RouteFacade;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls;
use Knuckles\Scribe\Scribe;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @coversNothing
 */
class ResponseCallsTest extends BaseLaravelTest
{
    /** @test */
    public function canCallRouteAndFetchResponse()
    {
        $route = LaravelRouteFacade::post('/shouldFetchRouteResponse', [TestController::class, 'shouldFetchRouteResponse']);

        $responses = $this->invokeStrategy($route);

        $this->assertEquals(200, $responses[0]['status']);
        $this->assertArraySubset([
            'id' => 4,
            'name' => 'banana',
            'color' => 'red',
            'weight' => '1 kg',
            'delicious' => true,
        ], json_decode($responses[0]['content'], true));
    }

    /** @test */
    public function canUploadFileParametersInResponseCalls()
    {
        $route = RouteFacade::post('/withFormDataParams', [TestController::class, 'withFormDataParams']);

        /* This doesn't work. It always gives an error, "the file failed to upload". However, uploading files worked when they are extracted params
        $endpointData = ExtractedEndpointData::fromRoute($route, [ 'headers' => [ 'accept' => 'application/json' ] ]);
        $responses = $this->invokeStrategy($endpointData, settings: [
            'fileParams' => [ 'image' => 'config/scribe.php' ],
            'bodyParams' => [ 'name' => 'cat.jpg' ]
        ]);
        */
        $this->setConfig([
            'strategies.responses' => [
                [ResponseCalls::class,
                    ['only' => 'POST *'],
                ],
            ],
        ]);
        $parsed = (new Extractor())->processRoute($route);
        $responses = $parsed->responses->toArray();

        $this->assertCount(1, $responses);
        $this->assertArraySubset([
            'status' => 200,
            'description' => null,
            'content' => '{"filename":"scribe.php","filepath":"config","name":"cat.jpg"}',
        ], $responses[0]);
    }

    /** @test */
    public function usesConfiguredSettingsWhenCallingRoute()
    {
        $route = LaravelRouteFacade::post('/echo/{id}', [TestController::class, 'echoesRequestValues']);

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'auth' => ['headers', 'Authorization', 'Bearer bearerToken'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'header' => 'headerValue',
            ],
        ]);

        $responses = $this->invokeStrategy($endpointData, settings: [
            'queryParams' => [
                'queryParam' => 'queryValue',
            ],
            'bodyParams' => [
                'bodyParam' => 'bodyValue',
            ],
        ]);

        $this->assertEquals(200, $responses[0]['status']);

        $responseContent = json_decode($responses[0]['content'], true);
        $this->assertEquals('queryValue', $responseContent['queryParam']);
        $this->assertEquals('bodyValue', $responseContent['bodyParam']);
        $this->assertEquals('headerValue', $responseContent['header']);
        $this->assertEquals('Bearer bearerToken', $responseContent['auth']);
    }

    /** @test */
    public function canOverrideApplicationConfigDuringResponseCall()
    {
        $route = LaravelRouteFacade::post('/echoesConfig', [TestController::class, 'echoesConfig']);
        $responses = $this->invokeStrategy($route);
        $originalValue = json_decode($responses[0]['content'], true)['app.env'];

        $now = time();
        $responses = $this->invokeStrategy($route, settings: [
            'config' => [
                'app.env' => $now,
            ],
        ], );
        $newValue = json_decode($responses[0]['content'], true)['app.env'];
        $this->assertEquals($now, $newValue);
        $this->assertNotEquals($originalValue, $newValue);
    }

    /** @test */
    public function callsBeforeResponseCallHook()
    {
        Scribe::beforeResponseCall(function (Request $request, ExtractedEndpointData $endpointData) {
            $request->headers->set('header', 'overridden_'.$request->headers->get('header'));
            $request->headers->set('Authorization', 'overridden_'.$request->headers->get('Authorization'));
            $request->query->set('queryParam', 'overridden_'.$request->query->get('queryParam'));
            $request->request->set('bodyParam', 'overridden_'.$endpointData->uri.$request->request->get('bodyParam'));
        });

        $route = LaravelRouteFacade::post('/echo/{id}', [TestController::class, 'echoesRequestValues']);

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'auth' => ['headers', 'Authorization', 'Bearer bearerToken'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'header' => 'headerValue',
            ],
        ]);
        $responses = $this->invokeStrategy($endpointData, settings: [
            'queryParams' => [
                'queryParam' => 'queryValue',
            ],
            'bodyParams' => [
                'bodyParam' => 'bodyValue',
            ],
        ]);

        $this->assertEquals(200, $responses[0]['status']);

        $responseContent = json_decode($responses[0]['content'], true);
        $this->assertEquals('overridden_queryValue', $responseContent['queryParam']);
        $this->assertEquals('overridden_headerValue', $responseContent['header']);
        $this->assertEquals('overridden_Bearer bearerToken', $responseContent['auth']);
        $this->assertEquals('overridden_echo/{id}bodyValue', $responseContent['bodyParam']);

        Scribe::beforeResponseCall(fn () => null);
    }

    /** @test */
    public function doesNotMakeResponseCallIfSuccessResponseAlreadyGotten()
    {
        $route = LaravelRouteFacade::post('/shouldFetchRouteResponse', [TestController::class, 'shouldFetchRouteResponse']);

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'responses' => new ResponseCollection([
                [
                    'status' => 200,
                    'content' => json_encode(['message' => 'LOL']),
                ],
            ]),
        ]);
        $responses = $this->invokeStrategy($endpointData);

        $this->assertNull($responses);
    }

    /** @test */
    public function canGetContentFromStreamedResponse()
    {
        $route = LaravelRouteFacade::post('/withStreamedResponse', [TestController::class, 'withStreamedResponse']);

        $this->withoutExceptionHandling();
        $responses = $this->invokeStrategy($route);

        $this->assertEquals(200, $responses[0]['status']);
        $this->assertArraySubset([
            'items' => [
                'one',
                'two',
            ],
        ], json_decode($responses[0]['content'], true));
    }

    protected function convertRules(array $rules): mixed
    {
        return Extractor::transformOldRouteRulesIntoNewSettings('responses', $rules, ResponseCalls::class);
    }

    protected function invokeStrategy(ExtractedEndpointData|Route $route, $settings = []): ?array
    {
        $strategy = new ResponseCalls(new DocumentationConfig([]));

        return $strategy(
            $route instanceof ExtractedEndpointData ? $route : ExtractedEndpointData::fromRoute($route),
            $settings
        );
    }
}
