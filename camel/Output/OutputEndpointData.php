<?php

namespace Knuckles\Camel\Output;

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Knuckles\Camel\BaseDTO;
use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Camel\Extraction\ResponseField;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Tools\Utils as u;
use Knuckles\Scribe\Tools\WritingUtils;


/**
 * Endpoint DTO, optimized for generating HTML output.
 * Unneeded properties removed, extra properties and helper methods added.
 */
class OutputEndpointData extends BaseDTO
{
    /**
     * @var array<string>
     */
    public array $httpMethods;

    public string $uri;

    public Metadata $metadata;

    /**
     * @var array<string,string>
     */
    public array $headers = [];

    /**
     * @var array<string,\Knuckles\Camel\Output\Parameter>
     */
    public array $urlParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanUrlParameters = [];

    /**
     * @var array<string,\Knuckles\Camel\Output\Parameter>
     */
    public array $queryParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanQueryParameters = [];

    /**
     * @var array<string, \Knuckles\Camel\Output\Parameter>
     */
    public array $bodyParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanBodyParameters = [];

    /**
     * @var array<string,\Illuminate\Http\UploadedFile>
     */
    public array $fileParameters = [];

    public ResponseCollection $responses;

    /**
     * @var array<string,\Knuckles\Camel\Extraction\ResponseField>
     */
    public array $responseFields = [];

    /**
     * The same as bodyParameters, but organized in a hierarchy.
     * So, top-level items first, with a __fields property containing their children, and so on.
     * Useful so we can easily render and nest on the frontend.
     * @var array<string, array>
     */
    public array $nestedBodyParameters = [];

    /**
     * @var array<string, array>
     */
    public array $nestedResponseFields = [];

    public ?string $boundUri;

    public function __construct(array $parameters = [])
    {
        // spatie/dto currently doesn't auto-cast nested DTOs like that
        $parameters['responses'] = new ResponseCollection($parameters['responses'] ?? []);
        $parameters['bodyParameters'] = array_map(fn($param) => new Parameter($param), $parameters['bodyParameters'] ?? []);
        $parameters['queryParameters'] = array_map(fn($param) => new Parameter($param), $parameters['queryParameters'] ?? []);
        $parameters['urlParameters'] = array_map(fn($param) => new Parameter($param), $parameters['urlParameters'] ?? []);
        $parameters['responseFields'] = array_map(fn($param) => new ResponseField($param), $parameters['responseFields'] ?? []);

        parent::__construct($parameters);

        $this->cleanBodyParameters = Extractor::cleanParams($this->bodyParameters);
        $this->cleanQueryParameters = Extractor::cleanParams($this->queryParameters);
        $this->cleanUrlParameters = Extractor::cleanParams($this->urlParameters);
        $this->nestedBodyParameters = self::nestArrayAndObjectFields($this->bodyParameters, $this->cleanBodyParameters);
        $this->nestedResponseFields = self::nestArrayAndObjectFields($this->responseFields);

        $this->boundUri = u::getUrlWithBoundParameters($this->uri, $this->cleanUrlParameters);

        [$files, $regularParameters] = static::splitIntoFileAndRegularParameters($this->cleanBodyParameters);

        if (count($files)) {
            $this->headers['Content-Type'] = 'multipart/form-data';
        }
        $this->fileParameters = $files;
        $this->cleanBodyParameters = $regularParameters;
    }

    /**
     * @param Route $route
     *
     * @return array<string>
     */
    public static function getMethods(Route $route): array
    {
        $methods = $route->methods();

        // Laravel adds an automatic "HEAD" endpoint for each GET request, so we'll strip that out,
        // but not if there's only one method (means it was intentional)
        if (count($methods) === 1) {
            return $methods;
        }

        return array_diff($methods, ['HEAD']);
    }

    public static function fromExtractedEndpointArray(array $endpoint): OutputEndpointData
    {
        return new self($endpoint);
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

    public function endpointId(): string
    {
        return $this->httpMethods[0] . str_replace(['/', '?', '{', '}', ':', '\\', '+', '|', '.'], '-', $this->uri);
    }

    public function name(): string
    {
        return $this->metadata->title ?: ($this->httpMethods[0] . " " . $this->uri);
    }

    public function fullSlug(): string
    {
        $groupSlug = Str::slug($this->metadata->groupName);
        $endpointId = $this->endpointId();
        return "$groupSlug-$endpointId";
    }

    public function hasResponses(): bool
    {
        return count($this->responses) > 0;
    }

    public function hasFiles(): bool
    {
        return count($this->fileParameters) > 0;
    }

    public function isArrayBody(): bool
    {
        return count($this->nestedBodyParameters) === 1
            && array_keys($this->nestedBodyParameters)[0] === "[]";
    }

    public function isGet(): bool
    {
        return in_array('GET', $this->httpMethods);
    }

    public function isAuthed(): bool
    {
        return $this->metadata->authenticated;
    }

    public function hasJsonBody(): bool
    {
        if ($this->hasFiles() || empty($this->nestedBodyParameters))
            return false;

        $contentType = data_get($this->headers, "Content-Type", data_get($this->headers, "content-type", ""));
        return str_contains($contentType, "json");
    }

    public function getSampleBody()
    {
        return WritingUtils::getSampleBody($this->nestedBodyParameters);
    }

    public function hasHeadersOrQueryOrBodyParams(): bool
    {
        return !empty($this->headers)
            || !empty($this->cleanQueryParameters)
            || !empty($this->cleanBodyParameters);
    }

    public static function splitIntoFileAndRegularParameters(array $parameters): array
    {
        $files = [];
        $regularParameters = [];
        foreach ($parameters as $name => $example) {
            if ($example instanceof UploadedFile) {
                $files[$name] = $example;
            } else if (is_array($example) && !empty($example)) {
                [$subFiles, $subRegulars] = static::splitIntoFileAndRegularParameters($example);
                foreach ($subFiles as $subName => $subExample) {
                    $files[$name][$subName] = $subExample;
                }
                foreach ($subRegulars as $subName => $subExample) {
                    $regularParameters[$name][$subName] = $subExample;
                }
            } else {
                $regularParameters[$name] = $example;
            }
        }
        return [$files, $regularParameters];
    }
}
