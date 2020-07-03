<?php

namespace Knuckles\Scribe\Extracting;

use Faker\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Utils as u;
use ReflectionClass;
use ReflectionFunctionAbstract;

class Generator
{
    /**
     * @var DocumentationConfig
     */
    private $config;

    public function __construct(DocumentationConfig $config = null)
    {
        // If no config is injected, pull from global
        $this->config = $config ?: new DocumentationConfig(config('scribe'));
    }

    /**
     * @param Route $route
     *
     * @return mixed
     */
    public function getUri(Route $route)
    {
        return $route->uri();
    }

    /**
     * @param Route $route
     *
     * @return mixed
     */
    public function getMethods(Route $route)
    {
        $methods = $route->methods();

        // Laravel adds an automatic "HEAD" endpoint for each GET request, so we'll strip that out,
        // but not if there's only one method (means it was intentional)
        if (count($methods) === 1) {
            return $methods;
        }

        return array_diff($methods, ['HEAD']);
    }

    /**
     * @param \Illuminate\Routing\Route $route
     * @param array $routeRules Rules to apply when generating documentation for this route
     *
     * @throws \ReflectionException
     *
     * @return array
     */
    public function processRoute(Route $route, array $routeRules = [])
    {
        [$controllerName, $methodName] = u::getRouteClassAndMethodNames($route);
        $controller = new ReflectionClass($controllerName);
        $method = u::getReflectedRouteMethod([$controllerName, $methodName]);

        $parsedRoute = [
            'id' => md5($this->getUri($route) . ':' . implode($this->getMethods($route))),
            'methods' => $this->getMethods($route),
            'uri' => $this->getUri($route),
        ];
        $metadata = $this->fetchMetadata($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['metadata'] = $metadata;

        $urlParameters = $this->fetchUrlParameters($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['urlParameters'] = $urlParameters;
        $parsedRoute['cleanUrlParameters'] = self::cleanParams($urlParameters);
        $parsedRoute['boundUri'] = u::getFullUrl($route, $parsedRoute['cleanUrlParameters']);

        $parsedRoute = $this->addAuthField($parsedRoute);

        $queryParameters = $this->fetchQueryParameters($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['queryParameters'] = $queryParameters;
        $parsedRoute['cleanQueryParameters'] = self::cleanParams($queryParameters);

        $headers = $this->fetchRequestHeaders($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['headers'] = $headers;

        $bodyParameters = $this->fetchBodyParameters($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['bodyParameters'] = $bodyParameters;
        $parsedRoute['cleanBodyParameters'] = self::cleanParams($bodyParameters);
        if (count($parsedRoute['cleanBodyParameters']) && !isset($parsedRoute['headers']['Content-Type'])) {
            // Set content type if the user forgot to set it
            $parsedRoute['headers']['Content-Type'] = 'application/json';
        }
        [$files, $regularParameters] = collect($parsedRoute['cleanBodyParameters'])->partition(function ($example) {
            return $example instanceof UploadedFile;
        });
        if (count($files)) {
            $parsedRoute['headers']['Content-Type'] = 'multipart/form-data';
        }
        $parsedRoute['fileParameters'] = $files->toArray();
        $parsedRoute['cleanBodyParameters'] = $regularParameters->toArray();

        $responses = $this->fetchResponses($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['responses'] = $responses;
        $parsedRoute['showresponse'] = ! empty($responses);

        $responseFields = $this->fetchResponseFields($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['responseFields'] = $responseFields;

        return $parsedRoute;
    }

    protected function fetchMetadata(ReflectionClass $controller, ReflectionFunctionAbstract $method, Route $route, array $rulesToApply, array $context = [])
    {
        $context['metadata'] = [
            'groupName' => $this->config->get('default_group', ''),
            'groupDescription' => '',
            'title' => '',
            'description' => '',
            'authenticated' => false,
        ];

        return $this->iterateThroughStrategies('metadata', $context, [$route, $controller, $method, $rulesToApply]);
    }

    protected function fetchUrlParameters(ReflectionClass $controller, ReflectionFunctionAbstract $method, Route $route, array $rulesToApply, array $context = [])
    {
        return $this->iterateThroughStrategies('urlParameters', $context, [$route, $controller, $method, $rulesToApply]);
    }

    protected function fetchQueryParameters(ReflectionClass $controller, ReflectionFunctionAbstract $method, Route $route, array $rulesToApply, array $context = [])
    {
        return $this->iterateThroughStrategies('queryParameters', $context, [$route, $controller, $method, $rulesToApply]);
    }

    protected function fetchBodyParameters(ReflectionClass $controller, ReflectionFunctionAbstract $method, Route $route, array $rulesToApply, array $context = [])
    {
        return $this->iterateThroughStrategies('bodyParameters', $context, [$route, $controller, $method, $rulesToApply]);
    }

    protected function fetchResponses(ReflectionClass $controller, ReflectionFunctionAbstract $method, Route $route, array $rulesToApply, array $context = [])
    {
        $responses = $this->iterateThroughStrategies('responses', $context, [$route, $controller, $method, $rulesToApply]);
        if (count($responses)) {
            return array_filter($responses, function ($response) {
                return $response['content'] != null;
            });
        }

        return [];
    }

    protected function fetchResponseFields(ReflectionClass $controller, ReflectionFunctionAbstract $method, Route $route, array $rulesToApply, array $context = [])
    {
        return $this->iterateThroughStrategies('responseFields', $context, [$route, $controller, $method, $rulesToApply]);
    }

    protected function fetchRequestHeaders(ReflectionClass $controller, ReflectionFunctionAbstract $method, Route $route, array $rulesToApply, array $context = [])
    {
        $headers = $this->iterateThroughStrategies('headers', $context, [$route, $controller, $method, $rulesToApply]);

        return array_filter($headers);
    }

    protected function iterateThroughStrategies(string $stage, array $extractedData, array $arguments)
    {
        $defaultStrategies = [
            'metadata' => [
                \Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks::class,
            ],
            'urlParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamTag::class,
            ],
            'queryParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag::class,
            ],
            'headers' => [
                \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromRouteRules::class,
                \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderTag::class,
            ],
            'bodyParameters' => [
                \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest::class,
                \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag::class,
            ],
            'responses' => [
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags::class,
                \Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls::class,
            ],
            'responseFields' => [
                \Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag::class,
            ],
        ];

        // Use the default strategies for the stage, unless they were explicitly set
        $strategies = $this->config->get("strategies.$stage", $defaultStrategies[$stage]);
        $extractedData[$stage] = $extractedData[$stage] ?? [];
        foreach ($strategies as $strategyClass) {
            /** @var Strategy $strategy */
            $strategy = new $strategyClass($this->config);
            $strategyArgs = $arguments;
            $strategyArgs[] = $extractedData;
            $results = $strategy(...$strategyArgs);
            if (! is_null($results)) {
                foreach ($results as $index => $item) {
                    if ($stage == 'responses') {
                        // Responses from different strategies are all added, not overwritten
                        $extractedData[$stage][] = $item;
                        continue;
                    }
                    // We're using a for loop rather than array_merge or +=
                    // so it does not renumber numeric keys and also allows values to be overwritten

                    // Don't allow overwriting if an empty value is trying to replace a set one
                    if (! in_array($extractedData[$stage], [null, ''], true) && in_array($item, [null, ''], true)) {
                        continue;
                    } else {
                        $extractedData[$stage][$index] = $item;
                    }
                }
            }
        }

        return $extractedData[$stage];
    }

    /**
     * This method prepares and simplifies request parameters for use in example requests and response calls.
     * It takes in an array with rich details about a parameter eg
     *   ['age' => [
     *     'description' => 'The age',
     *     'value' => 12,
     *     'required' => false,
     *   ]
     * And transforms them into key-value pairs : ['age' => 12]
     * It also filters out parameters which have null values and have 'required' as false.
     * It converts all file params that have string examples to actual files (instances of UploadedFile).
     * Finally, it adds a '.0' key for each array parameter (eg users.* ->users.0),
     * so that the array ends up containing a 1-item array.
     *
     * @param array $params
     *
     * @return array
     */
    public static function cleanParams(array $params): array
    {
        $cleanParams = [];

        // Remove params which have no examples and are optional.
        $params = array_filter($params, function ($details) {
            return ! (is_null($details['value']) && $details['required'] === false);
        });

        foreach ($params as $paramName => $details) {
            if (($details['type'] ?? '') === 'file' && is_string($details['value'])) {
                // Convert any string file examples to instances of UploadedFile
                $filePath = $details['value'];
                $fileName = basename($filePath);
                $details['value'] = new UploadedFile(
                    $filePath, $fileName, mime_content_type($filePath), 0,false
                );
            }
            self::generateConcreteKeysForArrayParameters(
                $paramName,
                $details['value'],
                $cleanParams
            );
        }

        return $cleanParams;
    }

    /**
     * For each array notation parameter (eg user.*, item.*.name, object.*.*, user[])
     * add a key that represents a "concrete" number (eg user.0, item.0.name, object.0.0, user.0 with the same value.
     * That way, we always have an array of length 1 for each array key
     *
     * @param string $paramName
     * @param mixed $paramExample
     * @param array $cleanParams The array that holds the result
     *
     * @return void
     */
    protected static function generateConcreteKeysForArrayParameters($paramName, $paramExample, array &$cleanParams = [])
    {
        if (Str::contains($paramName, '[')) {
            // Replace usages of [] with dot notation
            $paramName = str_replace(['][', '[', ']', '..'], ['.', '.', '', '.*.'], $paramName);
        }
        // Then generate a sample item for the dot notation
        Arr::set($cleanParams, str_replace(['.*', '*.'], ['.0','0.'], $paramName), $paramExample);
    }

    public function addAuthField(array $parsedRoute): array
    {
        $parsedRoute['auth'] = null;
        $isApiAuthed = $this->config->get('auth.enabled', false);
        if (!$isApiAuthed || !$parsedRoute['metadata']['authenticated']) {
            return $parsedRoute;
        }

        $strategy = $this->config->get('auth.in');
        $parameterName = $this->config->get('auth.name');

        $faker = Factory::create();
        if ($this->config->get('faker_seed')) {
            $faker->seed($this->config->get('faker_seed'));
        }
        $token = $faker->shuffle('abcdefghkvaZVDPE1864563');
        $valueToUse = $this->config->get('auth.use_value');
        switch ($strategy) {
            case 'query':
            case 'query_or_body':
                $parsedRoute['auth'] = "cleanQueryParameters.$parameterName.".($valueToUse ?: $token);
                $parsedRoute['queryParameters'][$parameterName] = [
                    'name' => $parameterName,
                    'value' => $token,
                    'description' => '',
                    'required' => true,
                ];
                break;
            case 'body':
                $parsedRoute['auth'] = "cleanBodyParameters.$parameterName.".($valueToUse ?: $token);
                $parsedRoute['bodyParameters'][$parameterName] = [
                    'name' => $parameterName,
                    'type' => 'string',
                    'value' => $token,
                    'description' => '',
                    'required' => true,
                ];
                break;
            case 'bearer':
                $parsedRoute['auth'] = "headers.Authorization.".($valueToUse ? "Bearer $valueToUse" : "Bearer $token");
                $parsedRoute['headers']['Authorization'] = "Bearer $token";
                break;
            case 'basic':
                $parsedRoute['auth'] = "headers.Authorization.".($valueToUse ? "Basic $valueToUse" : "Basic $token");
                $parsedRoute['headers']['Authorization'] = "Basic ".base64_encode($token);
                break;
            case 'header':
                $parsedRoute['auth'] = "headers.$parameterName.".($valueToUse ?: $token);
                $parsedRoute['headers'][$parameterName] = $token;
                break;
        }

        return $parsedRoute;
    }
}
