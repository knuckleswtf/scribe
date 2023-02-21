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
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Camel\Extraction\ResponseField;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\DocumentationConfig;

class Extractor
{
    private DocumentationConfig $config;

    use ParamHelpers;

    private static ?Route $routeBeingProcessed = null;

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
     *
     */
    public function processRoute(Route $route, array $routeRules = []): ExtractedEndpointData
    {
        self::$routeBeingProcessed = $route;

        $endpointData = ExtractedEndpointData::fromRoute($route);

        $inheritedDocsOverrides = [];
        if ($endpointData->controller->hasMethod('inheritedDocsOverrides')) {
            $inheritedDocsOverrides = call_user_func([$endpointData->controller->getName(), 'inheritedDocsOverrides']);
            $inheritedDocsOverrides = $inheritedDocsOverrides[$endpointData->method->getName()] ?? [];
        }

        $this->fetchMetadata($endpointData, $routeRules);
        $this->mergeInheritedMethodsData('metadata', $endpointData, $inheritedDocsOverrides);

        $this->fetchUrlParameters($endpointData, $routeRules);
        $this->mergeInheritedMethodsData('urlParameters', $endpointData, $inheritedDocsOverrides);
        $endpointData->cleanUrlParameters = self::cleanParams($endpointData->urlParameters);

        $this->addAuthField($endpointData);

        $this->fetchQueryParameters($endpointData, $routeRules);
        $this->mergeInheritedMethodsData('queryParameters', $endpointData, $inheritedDocsOverrides);
        $endpointData->cleanQueryParameters = self::cleanParams($endpointData->queryParameters);

        $this->fetchRequestHeaders($endpointData, $routeRules);
        $this->mergeInheritedMethodsData('headers', $endpointData, $inheritedDocsOverrides);

        $this->fetchBodyParameters($endpointData, $routeRules);
        $endpointData->cleanBodyParameters = self::cleanParams($endpointData->bodyParameters);
        $this->mergeInheritedMethodsData('bodyParameters', $endpointData, $inheritedDocsOverrides);

        if (count($endpointData->cleanBodyParameters) && !isset($endpointData->headers['Content-Type'])) {
            // Set content type if the user forgot to set it
            $endpointData->headers['Content-Type'] = 'application/json';
        }
        // We need to do all this so response calls can work correctly,
        // even though they're only needed for output
        // Note that this
        [$files, $regularParameters] = OutputEndpointData::splitIntoFileAndRegularParameters($endpointData->cleanBodyParameters);
        if (count($files)) {
            $endpointData->headers['Content-Type'] = 'multipart/form-data';
        }
        $endpointData->fileParameters = $files;
        $endpointData->cleanBodyParameters = $regularParameters;

        $this->fetchResponses($endpointData, $routeRules);
        $this->mergeInheritedMethodsData('responses', $endpointData, $inheritedDocsOverrides);

        $this->fetchResponseFields($endpointData, $routeRules);
        $this->mergeInheritedMethodsData('responseFields', $endpointData, $inheritedDocsOverrides);

        self::$routeBeingProcessed = null;

        return $endpointData;
    }

    protected function fetchMetadata(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $endpointData->metadata = new Metadata([
            'groupName' => $this->config->get('groups.default', ''),
            "authenticated" => $this->config->get("auth.default", false),
        ]);

        $this->iterateThroughStrategies('metadata', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            foreach ($results as $key => $item) {
                $hadPreviousValue = !is_null($endpointData->metadata->$key);
                $noNewValueSet = is_null($item) || $item === "";
                if ($hadPreviousValue && $noNewValueSet) {
                    continue;
                }
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
                $endpointData->urlParameters[$key] = Parameter::create($item, $endpointData->urlParameters[$key] ?? []);
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
                $endpointData->queryParameters[$key] = Parameter::create($item, $endpointData->queryParameters[$key] ?? []);
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
                $endpointData->bodyParameters[$key] = Parameter::create($item, $endpointData->bodyParameters[$key] ?? []);
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
        $endpointData->responses = new ResponseCollection($endpointData->responses->sortBy('status')->values());
    }

    protected function fetchResponseFields(ExtractedEndpointData $endpointData, array $rulesToApply): void
    {
        $this->iterateThroughStrategies('responseFields', $endpointData, $rulesToApply, function ($results) use ($endpointData) {
            foreach ($results as $key => $item) {
                $endpointData->responseFields[$key] = Parameter::create($item, $endpointData->responseFields[$key] ?? []);
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
        $strategies = $this->config->get("strategies.$stage", []);

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
                $cleanParameters[$paramName] = $details->example;
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
        if ($seed = $this->config->get('examples.faker_seed')) {
            $faker->seed($seed);
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
     * For instance, if $parameters is [
     *   'dad' => new Parameter(...),
     *   'dad.age' => new Parameter(...),
     *   'dad.cars[]' => new Parameter(...),
     *   'dad.cars[].model' => new Parameter(...),
     *   'dad.cars[].price' => new Parameter(...),
     * ],
     * normalise this into [
     *   'dad' => [
     *     ...,
     *     '__fields' => [
     *       'dad.age' => [...],
     *       'dad.cars' => [
     *         ...,
     *         '__fields' => [
     *           'model' => [...],
     *           'price' => [...],
     *         ],
     *       ],
     *   ],
     * ]]
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
                // If the user didn't add a parent field, we'll helpfully add it for them
                $ancestors = [];

                $parts = explode('.', $name);
                $fieldName = array_pop($parts);
                $parentName = rtrim(join('.', $parts), '[]');

                // When the body is an array, param names will be "[].paramname",
                // so $parentName is empty. Let's fix that.
                if (empty($parentName)) {
                    $parentName = '[]';
                }

                while ($parentName) {
                    if (!empty($normalisedParameters[$parentName])) {
                        break;
                    }

                    $details = [
                        "name" => $parentName,
                        "type" => $parentName === '[]' ? "object[]" : "object",
                        "description" => "",
                        "required" => false,
                    ];

                    if ($parameter instanceof ResponseField) {
                        $ancestors[] = [$parentName, new ResponseField($details)];
                    } else {
                        $lastParentExample = $details["example"] =
                            [$fieldName => $lastParentExample ?? $parameter->example];
                        $ancestors[] = [$parentName, new Parameter($details)];
                    }

                    $fieldName = array_pop($parts);
                    $parentName = rtrim(join('.', $parts), '[]');
                }

                // We add ancestors in reverse so we can iterate over parents first in the next section
                foreach (array_reverse($ancestors) as [$ancestorName, $ancestor]) {
                    $normalisedParameters[$ancestorName] = $ancestor;
                }
            }

            $normalisedParameters[$name] = $parameter;
            unset($lastParentExample);
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
                    $dotPathToParent = '[]' . $dotPathToParent;
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

    protected function mergeInheritedMethodsData(string $stage, ExtractedEndpointData $endpointData, array $inheritedDocsOverrides = []): void
    {
        $overrides = $inheritedDocsOverrides[$stage] ?? [];
        $normalizeParamData = fn($data, $key) => array_merge($data, ["name" => $key]);
        if (is_array($overrides)) {
            foreach ($overrides as $key => $item) {
                switch ($stage) {
                    case "responses":
                        $endpointData->responses->concat($overrides);
                        $endpointData->responses->sortBy('status');
                        break;
                    case "urlParameters":
                    case "bodyParameters":
                    case "queryParameters":
                        $endpointData->$stage[$key] = Parameter::make($normalizeParamData($item, $key));
                        break;
                    case "responseFields":
                        $endpointData->$stage[$key] = ResponseField::make($normalizeParamData($item, $key));
                        break;
                    default:
                        $endpointData->$stage[$key] = $item;
                }
            }
        } else if (is_callable($overrides)) {
            $results = $overrides($endpointData);

            $endpointData->$stage = match ($stage) {
                "responses" => ResponseCollection::make($results),
                "urlParameters", "bodyParameters", "queryParameters" => collect($results)->map(fn($param, $name) => Parameter::make($normalizeParamData($param, $name)))->all(),
                "responseFields" => collect($results)->map(fn($field, $name) => ResponseField::make($normalizeParamData($field, $name)))->all(),
                default => $results,
            };
        }
    }
}
