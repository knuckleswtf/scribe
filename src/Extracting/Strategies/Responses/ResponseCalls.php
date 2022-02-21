<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Illuminate\Support\Facades\Config;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Dingo\Api\Dispatcher;
use Dingo\Api\Routing\Route as DingoRoute;
use Exception;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Tools\Utils;

/**
 * Make a call to the route and retrieve its response.
 */
class ResponseCalls extends Strategy
{
    use ParamHelpers, DatabaseTransactionHelpers;

    protected array $previousConfigs = [];

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        return $this->makeResponseCallIfConditionsPass($endpointData, $routeRules);
    }

    public function makeResponseCallIfConditionsPass(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        $rulesToApply = $routeRules['response_calls'] ?? [];
        if (!$this->shouldMakeApiCall($endpointData, $rulesToApply)) {
            return null;
        }

        return $this->makeResponseCall($endpointData, $rulesToApply);
    }

    public function makeResponseCall(ExtractedEndpointData $endpointData, array $rulesToApply): ?array
    {
        $this->configureEnvironment($rulesToApply);

        // Mix in parsed parameters with manually specified parameters.
        $bodyParameters = array_merge($endpointData->cleanBodyParameters, $rulesToApply['bodyParams'] ?? []);
        $queryParameters = array_merge($endpointData->cleanQueryParameters, $rulesToApply['queryParams'] ?? []);
        $urlParameters = $endpointData->cleanUrlParameters;
        $headers = $endpointData->headers;

        if ($endpointData->auth) {
            [$where, $name, $value] = $endpointData->auth;
            switch ($where) {
                case 'queryParameters':
                    $queryParameters[$name] = $value;
                    break;
                case 'bodyParameters':
                    $bodyParameters[$name] = $value;
                    break;
                case 'headers':
                    $headers[$name] = $value;
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown auth location: $where");
            }
        }

        $hardcodedFileParams = $rulesToApply['fileParams'] ?? [];
        $hardcodedFileParams = collect($hardcodedFileParams)->map(function ($filePath) {
            $fileName = basename($filePath);
            return new UploadedFile(
                $filePath, $fileName, mime_content_type($filePath), 0, false
            );
        })->toArray();
        $fileParameters = array_merge($endpointData->fileParameters, $hardcodedFileParams);

        $request = $this->prepareRequest($endpointData->route, $rulesToApply, $urlParameters, $bodyParameters, $queryParameters, $fileParameters, $headers);

        $request = $this->runPreRequestHook($request, $endpointData);

        try {
            $response = $this->makeApiCall($request, $endpointData->route);
            $response = [
                [
                    'status' => $response->getStatusCode(),
                    'content' => $response->getContent(),
                    'headers' => $this->getResponseHeaders($response),
                ],
            ];
        } catch (Exception $e) {
            c::warn('Exception thrown during response call for' . $endpointData->name());
            e::dumpExceptionIfVerbose($e);

            $response = null;
        } finally {
            $this->finish();
        }

        return $response;
    }

    /**
     * @param array $rulesToApply
     *
     * @return void
     */
    private function configureEnvironment(array $rulesToApply)
    {
        $this->startDbTransaction();
        $this->setLaravelConfigs($rulesToApply['config'] ?? []);
    }

    /**
     * @param Route $route
     * @param array $rulesToApply
     * @param array $urlParams
     * @param array $bodyParams
     * @param array $queryParams
     *
     * @param array $fileParameters
     * @param array $headers
     *
     * @return Request
     */
    protected function prepareRequest(Route $route, array $rulesToApply, array $urlParams, array $bodyParams, array $queryParams, array $fileParameters, array $headers): Request
    {
        $uri = Utils::getUrlWithBoundParameters($route->uri(), $urlParams);
        $routeMethods = $this->getMethods($route);
        $method = array_shift($routeMethods);
        $cookies = $rulesToApply['cookies'] ?? [];

        // Note that we initialise the request with the bodyParams here
        // and later still add them to the ParameterBag (`addBodyParameters`)
        // The first is so the body params get added to the request content
        // (where Laravel reads body from)
        // The second is so they get added to the request bag
        // (where Symfony usually reads from and Laravel sometimes does)
        // Adding to both ensures consistency

        // Always use the current app domain for response calls
        $rootUrl = config('app.url');
        $request = Request::create("$rootUrl/$uri", $method, [], $cookies, $fileParameters, $this->transformHeadersToServerVars($headers), json_encode($bodyParams));
        // Doing it again to catch any ones we didn't transform properly.
        $request = $this->addHeaders($request, $route, $headers);

        $request = $this->addQueryParameters($request, $queryParams);
        $request = $this->addBodyParameters($request, $bodyParams);

        return $request;
    }

    protected function runPreRequestHook(Request $request, ExtractedEndpointData $endpointData): Request
    {
        if (is_callable(Globals::$__beforeResponseCall)) {
            call_user_func_array(Globals::$__beforeResponseCall, [$request, $endpointData]);
        }

        return $request;
    }

    private function setLaravelConfigs(array $config)
    {
        if (empty($config)) {
            return;
        }

        foreach ($config as $name => $value) {
            $this->previousConfigs[$name] = Config::get($name);
            Config::set([$name => $value]);
        }
    }

    private function rollbackLaravelConfigChanges()
    {
        foreach ($this->previousConfigs as $name => $value) {
            Config::set([$name => $value]);
        }
    }

    private function finish()
    {
        $this->endDbTransaction();
        $this->rollbackLaravelConfigChanges();
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function callDingoRoute(Request $request, Route $route)
    {
        /** @var \Dingo\Api\Dispatcher $dispatcher */
        $dispatcher = app(\Dingo\Api\Dispatcher::class);

        /** @var DingoRoute $route */
        $dispatcher->version($route->versions()[0]);
        foreach ($request->headers as $header => $value) {
            $dispatcher->header($header, $value);
        }

        // set domain and body parameters
        $dispatcher->on($request->header('SERVER_NAME'))
            ->with($request->request->all());

        // set URL and query parameters
        $uri = $request->getRequestUri();
        $query = $request->getQueryString();
        if (!empty($query)) {
            $uri .= "?$query";
        }

        $response = call_user_func_array(
            [$dispatcher, strtolower($request->method())],
            [$uri]
        );

        // the response from the Dingo dispatcher is the 'raw' response from the controller,
        // so we have to ensure it's JSON first
        if (!$response instanceof Response) {
            $response = response()->json($response);
        }

        return $response;
    }

    public function getMethods(Route $route): array
    {
        return array_diff($route->methods(), ['HEAD']);
    }

    private function addHeaders(Request $request, Route $route, ?array $headers): Request
    {
        // set the proper domain
        if ($route->getDomain()) {
            $request->headers->add([
                'HOST' => $route->getDomain(),
            ]);
            $request->server->add([
                'HTTP_HOST' => $route->getDomain(),
                'SERVER_NAME' => $route->getDomain(),
            ]);
        }

        $headers = collect($headers);

        if (($headers->get('Accept') ?: $headers->get('accept')) === 'application/json') {
            $request->setRequestFormat('json');
        }

        return $request;
    }

    private function addQueryParameters(Request $request, array $query): Request
    {
        $request->query->add($query);
        $request->server->add(['QUERY_STRING' => http_build_query($query)]);
        return $request;
    }

    private function addBodyParameters(Request $request, array $body): Request
    {
        $request->request->add($body);
        return $request;
    }

    /**
     * @param Request $request
     *
     * @param Route $route
     *
     * @return \Illuminate\Http\JsonResponse|mixed|\Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    protected function makeApiCall(Request $request, Route $route)
    {
        if ($this->config->get('router') == 'dingo') {
            $response = $this->callDingoRoute($request, $route);
        } else {
            $response = $this->callLaravelOrLumenRoute($request);
        }

        return $response;
    }

    protected function callLaravelOrLumenRoute(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        // Confirm we're running in Laravel, not Lumen
        if (app()->bound(Kernel::class)) {
            /** @var \Illuminate\Foundation\Http\Kernel $kernel */
            $kernel = app(Kernel::class);
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        } else {
            // Handle the request using the Lumen application.
            /** @var \Laravel\Lumen\Application $app */
            $app = app();
            $app->bind('request', function () use ($request) {
                return $request;
            });
            $response = $app->handle($request);
        }

        return $response;
    }

    protected function shouldMakeApiCall(ExtractedEndpointData $endpointData, array $rulesToApply): bool
    {
        $allowedMethods = $rulesToApply['methods'] ?? [];
        if (empty($allowedMethods)) {
            return false;
        }

        // Don't attempt a response call if there are already successful responses
        if ($endpointData->responses->hasSuccessResponse()) {
            return false;
        }

        if (is_string($allowedMethods) && $allowedMethods == '*') {
            return true;
        }

        if (array_search('*', $allowedMethods) !== false) {
            return true;
        }

        $routeMethods = $this->getMethods($endpointData->route);
        if (in_array(array_shift($routeMethods), $allowedMethods)) {
            return true;
        }

        return false;
    }

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     */
    protected function transformHeadersToServerVars(array $headers): array
    {
        $server = [];
        $prefix = 'HTTP_';
        foreach ($headers as $name => $value) {
            $name = strtr(strtoupper($name), '-', '_');
            if (!Str::startsWith($name, $prefix) && $name !== 'CONTENT_TYPE') {
                $name = $prefix . $name;
            }
            $server[$name] = $value;
        }

        return $server;
    }

    protected function getResponseHeaders($response): array
    {
        $headers = $response->headers->all();
        $formattedHeaders = [];

        foreach ($headers as $header => $values) {
            $formattedHeaders[$header] = implode('; ', $values);
        }

        return $formattedHeaders;
    }
}
