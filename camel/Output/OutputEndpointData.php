<?php

namespace Knuckles\Camel\Output;

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Knuckles\Camel\BaseDTO;
use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Camel\Extraction\ResponseField;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Tools\Utils as u;


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
     * @var array<string, array>
     */
    public array $nestedBodyParameters = [];

    public ?string $boundUri;

    public function __construct(array $parameters = [])
    {
        // spatie/dto currently doesn't auto-cast nested DTOs like that
        $parameters['responses'] = new ResponseCollection($parameters['responses']);
        $parameters['bodyParameters'] = array_map(function ($param) {
            return new Parameter($param);
        }, $parameters['bodyParameters']);
        $parameters['queryParameters'] = array_map(function ($param) {
            return new Parameter($param);
        }, $parameters['queryParameters']);
        $parameters['urlParameters'] = array_map(function ($param) {
            return new Parameter($param);
        }, $parameters['urlParameters']);
        $parameters['responseFields'] = array_map(function ($param) {
            return new ResponseField($param);
        }, $parameters['responseFields']);

        parent::__construct($parameters);

        $this->cleanBodyParameters = Extractor::cleanParams($this->bodyParameters);
        $this->cleanQueryParameters = Extractor::cleanParams($this->queryParameters);
        $this->cleanUrlParameters = Extractor::cleanParams($this->urlParameters);
        $this->nestedBodyParameters = Extractor::nestArrayAndObjectFields($this->bodyParameters, $this->cleanBodyParameters);

        $this->boundUri = u::getUrlWithBoundParameters($this->uri, $this->cleanUrlParameters);

        [$files, $regularParameters] = static::getFileParameters($this->cleanBodyParameters);

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

    public function endpointId(): string
    {
        return $this->httpMethods[0] . str_replace(['/', '?', '{', '}', ':', '\\', '+', '|'], '-', $this->uri);
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

    public function hasHeadersOrQueryOrBodyParams(): bool
    {
        return !empty($this->headers)
            || !empty($this->cleanQueryParameters)
            || !empty($this->cleanBodyParameters);
    }

    public static function getFileParameters(array $parameters): array
    {
        $files = [];
        $regularParameters = [];
        foreach ($parameters as $name => $example) {
            if ($example instanceof UploadedFile) {
                $files[$name] = $example;
            } else if (is_array($example) && !empty($example)) {
                [$subFiles, $subRegulars] = static::getFileParameters($example);
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