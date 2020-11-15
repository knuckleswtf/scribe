<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

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
use Knuckles\Scribe\Tools\Utils;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * Make a call to the route and retrieve its response.
 */
class ResponseCalls extends Strategy
{
    use ParamHelpers, DatabaseTransactionHelpers;

    /**
     * @param Route $route
     * @param ReflectionClass $controller
     * @param ReflectionFunctionAbstract $method
     * @param array $routeRules
     * @param array $alreadyExtractedData
     *
     * @return array|null
     */
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        return $this->makeResponseCallIfEnabledAndNoSuccessResponses($route, $routeRules, $alreadyExtractedData);
    }

    public function makeResponseCallIfEnabledAndNoSuccessResponses(Route $route, array $routeRules, array $context)
    {
        $rulesToApply = $routeRules['response_calls'] ?? [];
        if (!$this->shouldMakeApiCall($route, $rulesToApply, $context)) {
            return null;
        }

        return $this->makeResponseCall($route, $context, $rulesToApply);
    }

    public function makeResponseCall(Route $route, array $context, array $rulesToApply)
    {
        $this->configureEnvironment($rulesToApply);

        // Mix in parsed parameters with manually specified parameters.
        $context = $this->setAuthFieldProperly($context, $context['auth'] ?? null);
        $bodyParameters = array_merge($context['cleanBodyParameters'] ?? [], $rulesToApply['bodyParams'] ?? []);
        $queryParameters = array_merge($context['cleanQueryParameters'] ?? [], $rulesToApply['queryParams'] ?? []);
        $urlParameters = $context['cleanUrlParameters'] ?? [];

        $hardcodedFileParams = $rulesToApply['fileParams'] ?? [];
        $hardcodedFileParams = collect($hardcodedFileParams)->map(function ($filePath) {
            $fileName = basename($filePath);
            return new UploadedFile(
                $filePath, $fileName, mime_content_type($filePath), 0, false
            );
        })->toArray();
        $fileParameters = array_merge($context['fileParameters'] ?? [], $hardcodedFileParams);

        $request = $this->prepareRequest($route, $rulesToApply, $urlParameters, $bodyParameters, $queryParameters, $fileParameters, $context['headers'] ?? []);

        try {
            $response = $this->makeApiCall($request, $route);
            $response = [
                [
                    'status' => $response->getStatusCode(),
                    'content' => $response->getContent(),
                ],
            ];
        } catch (Exception $e) {
            c::warn('Exception thrown during response call for [' . implode(',', $route->methods) . "] {$route->uri}.");
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
    protected function prepareRequest(Route $route, array $rulesToApply, array $urlParams, array $bodyParams, array $queryParams, array $fileParameters, array $headers)
    {
        $uri = Utils::getUrlWithBoundParameters($route, $urlParams);
        $routeMethods = $this->getMethods($route);
        $method = array_shift($routeMethods);
        $cookies = isset($rulesToApply['cookies']) ? $rulesToApply['cookies'] : [];

        // Note that we initialise the request with the bodyParams here
        // and later still add them to the ParameterBag (`addBodyParameters`)
        // The first is so the body params get added to the request content
        // (where Laravel reads body from)
        // The second is so they get added to the request bag
        // (where Symfony usually reads from and Laravel sometimes does)
        // Adding to both ensures consistency

        $request = Request::create($uri, $method, [], $cookies, $fileParameters, $this->transformHeadersToServerVars($headers), json_encode($bodyParams));
        // Doing it again to catch any ones we didn't transform properly.
        $request = $this->addHeaders($request, $route, $headers);

        $request = $this->addQueryParameters($request, $queryParams);
        $request = $this->addBodyParameters($request, $bodyParams);

        return $request;
    }

    /**
     * @param array $config
     *
     * @return void
     */
    private function setLaravelConfigs(array $config)
    {
        if (empty($config)) {
            return;
        }

        foreach ($config as $name => $value) {
            config([$name => $value]);
        }
    }

    /**
     * @return void
     */
    private function finish()
    {
        $this->endDbTransaction();
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function callDingoRoute(Request $request, Route $route)
    {
        /** @var Dispatcher $dispatcher */
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

    /**
     * @param Route $route
     *
     * @return array
     */
    public function getMethods(Route $route)
    {
        return array_diff($route->methods(), ['HEAD']);
    }

    /**
     * @param Request $request
     * @param Route $route
     * @param array|null $headers
     *
     * @return Request
     */
    private function addHeaders(Request $request, Route $route, $headers)
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

    /**
     * @param Request $request
     * @param array $query
     *
     * @return Request
     */
    private function addQueryParameters(Request $request, array $query)
    {
        $request->query->add($query);
        $request->server->add(['QUERY_STRING' => http_build_query($query)]);

        return $request;
    }

    /**
     * @param Request $request
     * @param array $body
     *
     * @return Request
     */
    private function addBodyParameters(Request $request, array $body)
    {
        $request->request->add($body);

        return $request;
    }

    /**
     * @param array $context
     * @param string $authInfo in the format "<location>.<paramName>.<value>" eg "headers.Authorization.Bearer ahjuda"
     *
     * @return array
     */
    private function setAuthFieldProperly(array $context, ?string $authInfo)
    {
        if (!$authInfo) {
            return $context;
        }

        [$where, $name, $value] = explode('.', $authInfo, 3);
        $context[$where][$name] = $value;

        return $context;
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

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws Exception
     *
     */
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

    /**
     * @param Route $route
     * @param array $rulesToApply
     *
     * @return bool
     */
    protected function shouldMakeApiCall(Route $route, array $rulesToApply, array $context): bool
    {
        $allowedMethods = $rulesToApply['methods'] ?? [];
        if (empty($allowedMethods)) {
            return false;
        }

        // Don't attempt a response call if there are already successful responses
        $successResponses = collect($context['responses'] ?? [])->filter(function ($response) {
            return ((string)$response['status'])[0] == '2';
        })->count();
        if ($successResponses) {
            return false;
        }

        if (is_string($allowedMethods) && $allowedMethods == '*') {
            return true;
        }

        if (array_search('*', $allowedMethods) !== false) {
            return true;
        }

        $routeMethods = $this->getMethods($route);
        if (in_array(array_shift($routeMethods), $allowedMethods)) {
            return true;
        }

        return false;
    }

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @param array $headers
     *
     * @return array
     */
    protected function transformHeadersToServerVars(array $headers)
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
}
