<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Tools\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Make a call to the route and retrieve its response.
 */
class ResponseCalls extends Strategy
{
    use DatabaseTransactionHelpers;
    use ParamHelpers;

    protected array $previousConfigs = [];

    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        // Don't attempt a response call if there are already successful responses
        if ($endpointData->responses->hasSuccessResponse()) {
            return null;
        }

        return $this->makeResponseCall($endpointData, $settings);
    }

    public function makeResponseCall(ExtractedEndpointData $endpointData, array $settings): ?array
    {
        $this->configureEnvironment($settings);

        // Mix in parsed parameters with manually specified parameters.
        $bodyParameters = array_merge($endpointData->cleanBodyParameters, $settings['bodyParams'] ?? []);
        $queryParameters = array_merge($endpointData->cleanQueryParameters, $settings['queryParams'] ?? []);
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
                    throw new \InvalidArgumentException("Unknown auth location: {$where}");
            }
        }

        $hardcodedFileParams = $settings['fileParams'] ?? [];
        $hardcodedFileParams = collect($hardcodedFileParams)->map(function ($filePath) {
            $fileName = basename($filePath);

            return new UploadedFile(
                $filePath,
                $fileName,
                mime_content_type($filePath),
                test: true
            );
        })->toArray();
        $fileParameters = array_merge($endpointData->fileParameters, $hardcodedFileParams);

        $request = $this->prepareRequest(
            $endpointData->route,
            $endpointData->uri,
            $settings,
            $urlParameters,
            $bodyParameters,
            $queryParameters,
            $fileParameters,
            $headers
        );

        $this->runPreRequestHook($request, $endpointData);

        try {
            $response = $this->makeApiCall($request, $endpointData->route);

            $this->runPostRequestHook($request, $endpointData, $response);

            $response = [
                [
                    'status' => $response->getStatusCode(),
                    'content' => $this->getContentFromResponse($response),
                    'headers' => $this->getResponseHeaders($response),
                ],
            ];
        } catch (\Exception $e) {
            c::warn('Exception thrown during response call for'.$endpointData->name());
            e::dumpExceptionIfVerbose($e);

            $response = null;
        } finally {
            $this->finish();
        }

        return $response;
    }

    public function getMethods(Route $route): array
    {
        return array_diff($route->methods(), ['HEAD']);
    }

    /**
     * @param  array  $only  The routes which this strategy should be applied to. Can not be specified with $except.
     *                       Specify route names ("users.index", "users.*"), or method and path ("GET *", "POST /safe/*").
     * @param  array  $except  The routes which this strategy should be applied to. Can not be specified with $only.
     *                         Specify route names ("users.index", "users.*"), or method and path ("GET *", "POST /safe/*").
     * @param  array  $config  any extra Laravel config() values to before starting the response call
     * @param  array  $queryParams  Query params to always send with the response call. Key-value array.
     * @param  array  $bodyParams  Body params to always send with the response call. Key-value array.
     * @param  array  $fileParams  File params to always send with the response call. Key-value array. Key is param name, value is file path.
     * @param  array  $cookies  Cookies to always send with the response call. Key-value array.
     */
    public static function withSettings(
        array $only = [],
        array $except = [],
        array $config = [],
        array $queryParams = [],
        array $bodyParams = [],
        array $fileParams = [
            // 'key' => 'storage/app/image.png',
        ],
        array $cookies = [],
    ): array {
        return static::wrapWithSettings(
            only: $only,
            except: $except,
            otherSettings: compact(
                'config',
                'queryParams',
                'bodyParams',
                'fileParams',
                'cookies',
            )
        );
    }

    protected function prepareRequest(
        Route $route,
        string $url,
        array $settings,
        array $urlParams,
        array $bodyParams,
        array $queryParams,
        array $fileParameters,
        array $headers,
    ): Request {
        $uri = Utils::getUrlWithBoundParameters($url, $urlParams);
        $routeMethods = $this->getMethods($route);
        $method = array_shift($routeMethods);
        $cookies = $settings['cookies'] ?? [];

        // Note that we initialise the request with the bodyParams here
        // and later still add them to the ParameterBag (`addBodyParameters`)
        // The first is so the body params get added to the request content
        // (where Laravel reads body from)
        // The second is so they get added to the request bag
        // (where Symfony usually reads from and Laravel sometimes does)
        // Adding to both ensures consistency

        // Always use the current app domain for response calls
        $rootUrl = config('app.url');
        $request = Request::create(
            "{$rootUrl}/{$uri}",
            $method,
            [],
            $cookies,
            $fileParameters,
            $this->transformHeadersToServerVars($headers),
            json_encode($bodyParams)
        );
        // Add headers again to catch any ones we didn't transform properly.
        $this->addHeaders($request, $route, $headers);
        $this->addQueryParameters($request, $queryParams);
        $this->addBodyParameters($request, $bodyParams);

        return $request;
    }

    protected function runPreRequestHook(Request $request, ExtractedEndpointData $endpointData): void
    {
        if (is_callable(Globals::$__beforeResponseCall)) {
            call_user_func_array(Globals::$__beforeResponseCall, [$request, $endpointData]);
        }
    }

    protected function runPostRequestHook(Request $request, ExtractedEndpointData $endpointData, mixed $response): void
    {
        if (is_callable(Globals::$__afterResponseCall)) {
            call_user_func_array(Globals::$__afterResponseCall, [$request, $endpointData, $response]);
        }
    }

    /**
     * @return Response
     *
     * @throws \Exception
     */
    protected function makeApiCall(Request $request, Route $route)
    {
        return $this->callLaravelRoute($request);
    }

    protected function callLaravelRoute(Request $request): Response
    {
        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     */
    protected function transformHeadersToServerVars(array $headers): array
    {
        $server = [];
        $prefix = 'HTTP_';
        foreach ($headers as $name => $value) {
            $name = strtr(mb_strtoupper($name), '-', '_');
            if (! Str::startsWith($name, $prefix) && $name !== 'CONTENT_TYPE') {
                $name = $prefix.$name;
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

    protected function getContentFromResponse(Response $response): false|string
    {
        if (! $response instanceof StreamedResponse) {
            return $response->getContent();
        }

        // For streamed responses, the content is null, and only output directly via "echo" when we call "sendContent".
        // We use output buffering to capture the output into a new fake response.
        $renderedResponse = new Response('', $response->getStatusCode());
        $originalCallback = $response->getCallback();
        $response->setCallback(function () use ($originalCallback, $renderedResponse) {
            ob_start(function ($output) use ($renderedResponse) {
                $renderedResponse->setContent($output);
            });
            $originalCallback();
            ob_end_flush();
        });
        $response->sendContent();
        $renderedResponse->headers = $response->headers;

        return $renderedResponse->getContent();
    }

    private function configureEnvironment(array $settings)
    {
        $this->startDbTransaction();
        $this->setLaravelConfigs($settings['config'] ?? []);
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

    private function addHeaders(Request $request, Route $route, ?array $headers): void
    {
        // Set the proper domain
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
    }

    private function addQueryParameters(Request $request, array $query): void
    {
        $request->query->add($query);
        $request->server->add(['QUERY_STRING' => http_build_query($query)]);
    }

    private function addBodyParameters(Request $request, array $body): void
    {
        $request->request->add($body);
    }
}
