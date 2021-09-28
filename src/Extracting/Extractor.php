<?php

namespace Knuckles\Scribe\Extracting;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Extraction\Parameter;
use Faker\Factory;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\ResponseField;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\DocumentationConfig;

class Extractor
{
    private DocumentationConfig $config;

    use ParamHelpers;

    private static ?Route $routeBeingProcessed = null;

    private static array $defaultStrategies = [
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
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromInlineValidator::class,
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
     * @param array $routeRules Rules to apply when generating documentation for this route
     *
     * @return ExtractedEndpointData
     * @throws \ReflectionException
     *
     */
    public function processRoute(Route $route, array $routeRules = []): ExtractedEndpointData
    {
        self::$routeBeingProcessed = $route;

        $endpointData = ExtractedEndpointData::fromRoute($route);
        $this->fetchMetadata($endpointData, $routeRules);

        $this->fetchUrlParameters($endpointData, $routeRules);
        $endpointData->cleanUrlParameters = self::cleanParams($endpointData->urlParameters);

        $this->addAuthField($endpointData);

        $this->fetchQueryParameters($endpointData, $routeRules);
        $endpointData->cleanQueryParameters = self::cleanParams($endpointData->queryParameters);

        $this->fetchRequestHeaders($endpointData, $routeRules);

        $this->fetchBodyParameters($endpointData, $routeRules);
        $endpointData->cleanBodyParameters = self::cleanParams($endpointData->bodyParameters);

        if (count($endpointData->cleanBodyParameters) && !isset($endpointData->headers['Content-Type'])) {
            // Set content type if the user forgot to set it
            $endpointData->headers['Content-Type'] = 'application/json';
        }
        // We need to do all this so response calls can work correctly,
        // even though they're only needed for output
        // Note that this
        [$files, $regularParameters] = OutputEndpointData::getFileParameters($endpointData->cleanBodyParameters);
        if (count($files)) {
            $endpointData->headers['Content-Type'] = 'multipart/form-data';
        }
        $endpointData->fileParameters = $files;
        $endpointData->cleanBodyParameters = $regularParameters;

        $this->fetchResponses($endpointData, $routeRules);

        $this->fetchResponseFields($endpointData, $routeRules);

        self::$routeBeingProcessed = null;

        return $endpointData;
    }

    protected function fetchMetadata(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $endpointData->metadata = new Metadata([
            'groupName' => $this->config->get('default_group', ''),
        ]);

        $this->iterateThroughStrategies('metadata', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            foreach ($results as $key => $item) {
                $endpointData->metadata->$key = $item;
            }
        });
    }

    protected function fetchUrlParameters(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $this->iterateThroughStrategies('urlParameters', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            foreach ($results as $key => $item) {
                if (empty($item['name'])) {
                    $item['name'] = $key;
                }
                $endpointData->urlParameters[$key] = Parameter::create($item);
            }
        });
    }

    protected function fetchQueryParameters(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $this->iterateThroughStrategies('queryParameters', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            foreach ($results as $key => $item) {
                if (empty($item['name'])) {
                    $item['name'] = $key;
                }
                $endpointData->queryParameters[$key] = Parameter::create($item);
            }
        });
    }

    protected function fetchBodyParameters(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $this->iterateThroughStrategies('bodyParameters', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            foreach ($results as $key => $item) {
                if (empty($item['name'])) {
                    $item['name'] = $key;
                }
                $endpointData->bodyParameters[$key] = Parameter::create($item);
            }
        });
    }

    protected function fetchResponses(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $this->iterateThroughStrategies('responses', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            // Responses from different strategies are all added, not overwritten
            $endpointData->responses->concat($results);
        });
        // Ensure 200 responses come first
        $endpointData->responses->sortBy('status');
    }

    protected function fetchResponseFields(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $this->iterateThroughStrategies('responseFields', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            foreach ($results as $key => $item) {
                $endpointData->responseFields[$key] = ResponseField::create($item);
            }
        });
    }

    protected function fetchRequestHeaders(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $this->iterateThroughStrategies('headers', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            foreach ($results as $key => $item) {
                if ($item) {
                    $endpointData->headers[$key] = $item;
                }
            }
        });
    }

    /**
     * Iterate through all defined strategies for this stage.
     * A strategy may return an array of attributes
     * to be added to that stage data, or it may modify the stage data directly.
     *
     * @param string $stage
     * @param ExtractedEndpointData $endpointData
     * @param array $rulesToApply
     * @param callable $handler Function to run after each strategy returns its results (an array).
     *
     */
    protected function iterateThroughStrategies(string $stage, ExtractedEndpointData $endpointData, array $rulesToApply, callable $handler): void
    {
        $strategies = $this->config->get("strategies.$stage", self::$defaultStrategies[$stage]);

        foreach ($strategies as $strategyClass) {
            /** @var Strategy $strategy */
            $strategy = new $strategyClass($this->config);
            $results = $strategy($endpointData, $rulesToApply);
            if (is_array($results)) {
                $handler($results);
            }
        }
    }

    /**
     * This method prepares and simplifies request parameters for use in example requests and response calls.
     * It takes in an array with rich details about a parameter eg
     *   ['age' => new Parameter([
     *     'description' => 'The age',
     *     'example' => 12,
     *     'required' => false,
     *   ])]
     * And transforms them into key-example pairs : ['age' => 12]
     * It also filters out parameters which have null values and have 'required' as false.
     * It converts all file params that have string examples to actual files (instances of UploadedFile).
     *
     * @param array<string,Parameter> $parameters
     *
     * @return array
     */
    public static function cleanParams(array $parameters): array
    {
        $cleanParameters = [];

        /**
         * @var string $paramName
         * @var Parameter $details
         */
        foreach ($parameters as $paramName => $details) {
            // Remove params which have no examples and are optional.
            if (is_null($details->example) && $details->required === false) {
                continue;
            }

            if ($details->type === 'file') {
                if (is_string($details->example)) {
                    $details->example = self::convertStringValueToUploadedFileInstance($details->example);
                } else if (is_null($details->example)) {
                    $details->example = (new self)->generateDummyValue($details->type);
                }
            }

            if (Str::startsWith($paramName, '[].')) { // Entire body is an array
                if (empty($parameters["[]"])) { // Make sure there's a parent
                    $cleanParameters["[]"] = [[], []];
                    $parameters["[]"] = new Parameter([
                        "name" => "[]",
                        "type" => "object[]",
                        "description" => "",
                        "required" => true,
                        "example" => [$paramName => $details->example],
                    ]);
                }
            }

            if (Str::contains($paramName, '.')) { // Object field (or array of objects)
                self::setObject($cleanParameters, $paramName, $details->example, $parameters, $details->required);
            } else {
                $cleanParameters[$paramName] = $details->example instanceof \stdClass ? $details->example : $details->example;
            }
        }

        // Finally, if the body is an array, flatten it.
        if (isset($cleanParameters['[]'])) {
            $cleanParameters = $cleanParameters['[]'];
        }

        return $cleanParameters;
    }

    public static function setObject(array &$results, string $path, $value, array $source, bool $isRequired)
    {
        $parts = explode('.', $path);

        array_pop($parts); // Get rid of the field name

        $baseName = join('.', $parts);
        // For array fields, the type should be indicated in the source object by now;
        // eg test.items[] would actually be described as name: test.items, type: object[]
        // So we get rid of that ending []
        // For other fields (eg test.items[].name), it remains as-is
        $baseNameInOriginalParams = $baseName;
        while (Str::endsWith($baseNameInOriginalParams, '[]')) {
            $baseNameInOriginalParams = substr($baseNameInOriginalParams, 0, -2);
        }
        // When the body is an array, param names will be  "[].paramname",
        // so $baseNameInOriginalParams here will be empty
        if (Str::startsWith($path, '[].')) {
            $baseNameInOriginalParams = '[]';
        }

        if (Arr::has($source, $baseNameInOriginalParams)) {
            /** @var Parameter $parentData */
            $parentData = Arr::get($source, $baseNameInOriginalParams);
            // Path we use for data_set
            $dotPath = str_replace('[]', '.0', $path);

            // Don't overwrite parent if there's already data there

            if ($parentData->type === 'object') {
                $parentPath = explode('.', $dotPath);
                $property = array_pop($parentPath);
                $parentPath = implode('.', $parentPath);

                $exampleFromParent = Arr::get($results, $dotPath) ?? $parentData->example[$property] ?? null;
                if (empty($exampleFromParent)) {
                    Arr::set($results, $dotPath, $value);
                }
            } else if ($parentData->type === 'object[]') {
                // When the body is an array, param names will be  "[].paramname", so dot paths won't work correctly with "[]"
                if (Str::startsWith($path, '[].')) {
                    $valueDotPath = substr($dotPath, 3); // Remove initial '.0.'
                    if (isset($results['[]'][0]) && !Arr::has($results['[]'][0], $valueDotPath)) {
                        Arr::set($results['[]'][0], $valueDotPath, $value);
                    }
                } else {
                    $parentPath = explode('.', $dotPath);
                    $index = (int)array_pop($parentPath);
                    $parentPath = implode('.', $parentPath);

                    $exampleFromParent = Arr::get($results, $dotPath) ?? $parentData->example[$index] ?? null;
                    if (empty($exampleFromParent)) {
                        Arr::set($results, $dotPath, $value);
                    }
                }
            }
        }
    }

    public function addAuthField(ExtractedEndpointData $endpointData): void
    {
        $isApiAuthed = $this->config->get('auth.enabled', false);
        if (!$isApiAuthed || !$endpointData->metadata->authenticated) {
            return;
        }

        $strategy = $this->config->get('auth.in');
        $parameterName = $this->config->get('auth.name');

        $faker = Factory::create();
        if ($this->config->get('faker_seed')) {
            $faker->seed($this->config->get('faker_seed'));
        }
        $token = $faker->shuffleString('abcdefghkvaZVDPE1864563');
        $valueToUse = $this->config->get('auth.use_value');
        $valueToDisplay = $this->config->get('auth.placeholder');

        switch ($strategy) {
            case 'query':
            case 'query_or_body':
                $endpointData->auth = ["queryParameters", $parameterName, $valueToUse ?: $token];
                $endpointData->queryParameters[$parameterName] = new Parameter([
                    'name' => $parameterName,
                    'type' => 'string',
                    'example' => $valueToDisplay ?: $token,
                    'description' => 'Authentication key.',
                    'required' => true,
                ]);
                return;
            case 'body':
                $endpointData->auth = ["bodyParameters", $parameterName, $valueToUse ?: $token];
                $endpointData->bodyParameters[$parameterName] = new Parameter([
                    'name' => $parameterName,
                    'type' => 'string',
                    'example' => $valueToDisplay ?: $token,
                    'description' => 'Authentication key.',
                    'required' => true,
                ]);
                return;
            case 'bearer':
                $endpointData->auth = ["headers", "Authorization", "Bearer " . ($valueToUse ?: $token)];
                $endpointData->headers['Authorization'] = "Bearer " . ($valueToDisplay ?: $token);
                return;
            case 'basic':
                $endpointData->auth = ["headers", "Authorization", "Basic " . ($valueToUse ?: base64_encode($token))];
                $endpointData->headers['Authorization'] = "Basic " . ($valueToDisplay ?: base64_encode($token));
                return;
            case 'header':
                $endpointData->auth = ["headers", $parameterName, $valueToUse ?: $token];
                $endpointData->headers[$parameterName] = $valueToDisplay ?: $token;
                return;
        }
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
     *
     * @param array $parameters
     *
     * @return array
     */
    public static function nestArrayAndObjectFields(array $parameters, array $cleanParameters = []): array
    {
        // First, we'll make sure all object fields have parent fields properly set
        $normalisedParameters = [];
        foreach ($parameters as $name => $parameter) {
            if (Str::contains($name, '.')) {
                // Get the various pieces of the name
                $parts = explode('.', $name);
                $fieldName = array_pop($parts);

                // If the user didn't add a parent field, we'll helpfully add it for them
                $parentName = rtrim(join('.', $parts), '[]');

                // When the body is an array, param names will be "[].paramname",
                // so $parentName is empty. Let's fix that.
                if (empty($parentName)) {
                    $parentName = '[]';
                }

                if (empty($normalisedParameters[$parentName])) {
                    $normalisedParameters[$parentName] = new Parameter([
                        "name" => $parentName,
                        "type" => $parentName === '[]' ? "object[]" : "object",
                        "description" => "",
                        "required" => true,
                        "example" => [$fieldName => $parameter->example],
                    ]);
                }
            }
            $normalisedParameters[$name] = $parameter;
        }

        $finalParameters = [];
        foreach ($normalisedParameters as $name => $parameter) {
            $parameter = $parameter->toArray();
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
                // When the body is an array, param names will be  "[].paramname",
                // so $parts is ['[]']
                if ($parts[0] == '[]') {
                    $dotPathToParent = '[]'.$dotPathToParent;
                }

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

        // Finally, if the body is an array, remove any other items.
        if (isset($finalParameters['[]'])) {
            $finalParameters = ["[]" => $finalParameters['[]']];
            // At this point, the examples are likely [[], []],
            // but have been correctly set in clean parameters, so let's update them
            if ($finalParameters["[]"]["example"][0] == [] && !empty($cleanParameters)) {
                $finalParameters["[]"]["example"] = $cleanParameters;
            }
        }

        return $finalParameters;
    }
}
