<?php

namespace Knuckles\Scribe\Extracting;

use Faker\Factory;
use Illuminate\Http\Testing\File;
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

    /**
     * @var Route|null
     */
    private static $routeBeingProcessed = null;

    public function __construct(DocumentationConfig $config = null)
    {
        // If no config is injected, pull from global
        $this->config = $config ?: new DocumentationConfig(config('scribe'));
    }

    /**
     * External interface that allows users to know what route is currently being processed
     */
    public static function getRouteBeingProcessed(): ?Route
    {
        return self::$routeBeingProcessed;
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
     * @return array
     * @throws \ReflectionException
     *
     */
    public function processRoute(Route $route, array $routeRules = [])
    {
        self::$routeBeingProcessed = $route;

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
        $parsedRoute['boundUri'] = u::getUrlWithBoundParameters($route, $parsedRoute['cleanUrlParameters']);

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
            return $example instanceof UploadedFile
                || (is_array($example) && !empty($example[0]) && $example[0] instanceof UploadedFile);
        });
        if (count($files)) {
            $parsedRoute['headers']['Content-Type'] = 'multipart/form-data';
        }
        $parsedRoute['fileParameters'] = $files->toArray();
        $parsedRoute['cleanBodyParameters'] = $regularParameters->toArray();

        $responses = $this->fetchResponses($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['responses'] = $responses;
        $parsedRoute['showresponse'] = !empty($responses);

        $responseFields = $this->fetchResponseFields($controller, $method, $route, $routeRules, $parsedRoute);
        $parsedRoute['responseFields'] = $responseFields;


        $parsedRoute['nestedBodyParameters'] = self::nestArrayAndObjectFields($parsedRoute['bodyParameters']);

        self::$routeBeingProcessed = null;

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
                \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI::class,
                \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLumenAPI::class,
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
            if (!is_null($results)) {
                foreach ($results as $index => $item) {
                    if ($stage == 'responses') {
                        // Responses from different strategies are all added, not overwritten
                        $extractedData[$stage][] = $item;
                        continue;
                    }
                    // We're using a for loop rather than array_merge or +=
                    // so it does not renumber numeric keys and also allows values to be overwritten

                    // Don't allow overwriting if an empty value is trying to replace a set one
                    if (!in_array($extractedData[$stage], [null, ''], true) && in_array($item, [null, ''], true)) {
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
     *   ]]
     * And transforms them into key-example pairs : ['age' => 12]
     * It also filters out parameters which have null values and have 'required' as false.
     * It converts all file params that have string examples to actual files (instances of UploadedFile).
     *
     * @param array $parameters
     *
     * @return array
     */
    public static function cleanParams(array $parameters): array
    {
        $cleanParameters = [];

        foreach ($parameters as $paramName => $details) {
            // Remove params which have no examples and are optional.
            if (is_null($details['value']) && $details['required'] === false) {
                continue;
            }

            if (($details['type'] ?? '') === 'file' && is_string($details['value'])) {
                $details['value'] = self::convertStringValueToUploadedFileInstance($details['value']);
            }

            if (Str::contains($paramName, '.')) { // Object field (or array of objects)
                self::setObject($cleanParameters, $paramName, $details['value'], $parameters, ($details['required'] ?? false));
            } else {
                $cleanParameters[$paramName] = $details['value'];
            }
        }

        return $cleanParameters;
    }

    public static function setObject(array &$results, string $path, $value, array $source, bool $isRequired)
    {
        $parts = explode('.', $path);

        $paramName = array_pop($parts); // Remove the field name

        $baseName = join('.', $parts);
        // For array fields, the type should be indicated in the source object by now;
        // eg test.items[] would actually be described as name: test.items, type: object[]
        // So we get rid of that ending []
        // For other fields (eg test.items[].name), it remains as-is
        $baseNameInOriginalParams = $baseName;
        while (Str::endsWith($baseNameInOriginalParams, '[]')) {
            $baseNameInOriginalParams = substr($baseNameInOriginalParams, 0, -2);
        }

        if (empty($baseNameInOriginalParams)) {
            // If this is empty, it indicates that the body is an array of objects. (i.e. "[].param")
            // Therefore, each parameter should be an element of the first object in that array.
            $results[0][$paramName] = $value;
        } elseif (Str::startsWith($path, '[]')) {
            // If the body is an array, then any top level parameters (i.e. "[].param") would have been handled by the previous block
            // Therefore, we assume that this is a child parameter (i.e. "[].parent.child" or "[].parent[].child"

            // Remove the top-level array brackets
            $dotPath = substr($path, 3);
            // Use correct dot notation for any child arrays
            $dotPath = '0.' . str_replace('[]', '.0', $dotPath);
            Arr::set($results, $dotPath, $value);
        } elseif (Arr::has($source, $baseNameInOriginalParams)) {
            $parentData = Arr::get($source, $baseNameInOriginalParams);
            // Path we use for data_set
            $dotPath = str_replace('[]', '.0', $path);
            if ($parentData['type'] === 'object') {
                if (!Arr::has($results, $dotPath)) {
                    Arr::set($results, $dotPath, $value);
                }
            } else if ($parentData['type'] === 'object[]') {
                if (!Arr::has($results, $dotPath)) {
                    Arr::set($results, $dotPath, $value);
                }
                // If there's a second item in the array, set for that too.
                if ($value !== null && Arr::has($results, Str::replaceLast('[]', '.1', $baseName))) {
                    // If value is optional, flip a coin on whether to set or not
                    if ($isRequired || array_rand([true, false], 1)) {
                        Arr::set($results, Str::replaceLast('.0', '.1', $dotPath), $value);
                    }
                }
            }
        }
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
        $valueToDisplay = $this->config->get('auth.placeholder');

        switch ($strategy) {
            case 'query':
            case 'query_or_body':
                $parsedRoute['auth'] = "cleanQueryParameters.$parameterName." . ($valueToUse ?: $token);
                $parsedRoute['queryParameters'][$parameterName] = [
                    'name' => $parameterName,
                    'type' => 'string',
                    'value' => $valueToDisplay ?: $token,
                    'description' => 'Authentication key.',
                    'required' => true,
                ];
                break;
            case 'body':
                $parsedRoute['auth'] = "cleanBodyParameters.$parameterName." . ($valueToUse ?: $token);
                $parsedRoute['bodyParameters'][$parameterName] = [
                    'name' => $parameterName,
                    'type' => 'string',
                    'value' => $valueToDisplay ?: $token,
                    'description' => 'Authentication key.',
                    'required' => true,
                ];
                break;
            case 'bearer':
                $parsedRoute['auth'] = "headers.Authorization.Bearer " . ($valueToUse ?: $token);
                $parsedRoute['headers']['Authorization'] = "Bearer " . ($valueToDisplay ?: $token);
                break;
            case 'basic':
                $parsedRoute['auth'] = "headers.Authorization.Basic " . ($valueToUse ?: base64_encode($token));
                $parsedRoute['headers']['Authorization'] = "Basic " . ($valueToDisplay ?: base64_encode($token));
                break;
            case 'header':
                $parsedRoute['auth'] = "headers.$parameterName." . ($valueToUse ?: $token);
                $parsedRoute['headers'][$parameterName] = $valueToDisplay ?: $token;
                break;
        }

        return $parsedRoute;
    }

    protected static function convertStringValueToUploadedFileInstance(string $filePath): UploadedFile
    {
        $fileName = basename($filePath);
        return new File($fileName, fopen($filePath, 'r'));
    }

    /**
     * Transform body parameters such that object fields have a `fields` property containing a list of all subfields
     * Subfields will be removed from the main parameter map
     * For instance, if $parameters is ['dad' => [], 'dad.cars' => [], 'dad.age' => []],
     * normalise this into ['dad' => [..., '__fields' => ['dad.cars' => [], 'dad.age' => []]]
     */
    public static function nestArrayAndObjectFields(array $parameters)
    {
        // First, we'll make sure all object fields have parent fields properly set
        $normalisedParameters = [];
        foreach ($parameters as $name => $parameter) {
            if (Str::contains($name, '.')) {
                // Get the various pieces of the name
                $parts = explode('.', $name);
                $fieldName = array_pop($parts);

                // If the user didn't add a parent field, we'll conveniently add it for them
                $parentName = rtrim(join('.', $parts), '[]');
                if (!empty($parentName) && empty($parameters[$parentName])) {
                    $normalisedParameters[$parentName] = [
                        "name" => $parentName,
                        "type" => "object",
                        "description" => "",
                        "required" => false,
                        "value" => [$fieldName => $parameter['value']],
                    ];
                }

                // If the body is an array, the parameter array must use sequential keys
                if (Str::startsWith($name, '[].')) {
                  $name = count($normalisedParameters);
                }
            }
            $normalisedParameters[$name] = $parameter;
        }

        $finalParameters = [];
        foreach ($normalisedParameters as $name => $parameter) {
            if (Str::contains($name, '.')) { // An object field
                // Get the various pieces of the name
                $parts = explode('.', $name);
                $fieldName = array_pop($parts);
                $baseName = join('.__fields.', $parts);

                // For subfields, the type is indicated in the source object
                // eg test.items[].more and test.items.more would both have parent field with name `items` and containing __fields => more
                // The difference would be in the parent field's `type` property (object[] vs object)
                // So we can get rid of all [] to get the parent name
                $dotPathToParent = str_replace('[]', '', $baseName);

                $dotPath = $dotPathToParent . '.__fields.' . $fieldName;
                Arr::set($finalParameters, $dotPath, $parameter);
            } else { // A regular field, not a subfield of anything
                // Note: we're assuming any subfields of this field are listed *after* it,
                // and will set __fields correctly when we iterate over them
                // Hence why we create a new "normalisedParameters" array above and push the parent to that first
                $parameter['__fields'] = [];
                $finalParameters[$name] = $parameter;
            }

        }

        return $finalParameters;
    }
}
