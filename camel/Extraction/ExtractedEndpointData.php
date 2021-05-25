<?php

namespace Knuckles\Camel\Extraction;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Knuckles\Camel\BaseDTO;
use Knuckles\Scribe\Tools\Utils as u;
use ReflectionClass;


class ExtractedEndpointData extends BaseDTO
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
     * @var array<string,\Knuckles\Camel\Extraction\Parameter>
     */
    public array $urlParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanUrlParameters = [];

    /**
     * @var array<string,\Knuckles\Camel\Extraction\Parameter>
     */
    public array $queryParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanQueryParameters = [];

    /**
     * @var array<string,\Knuckles\Camel\Extraction\Parameter>
     */
    public array $bodyParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanBodyParameters = [];

    /**
     * @var array<string,\Illuminate\Http\UploadedFile|array>
     */
    public array $fileParameters = [];

    /**
     * @var ResponseCollection|array
     */
    public $responses;

    /**
     * @var array<string,\Knuckles\Camel\Extraction\ResponseField>
     */
    public array $responseFields = [];

    /**
     * Authentication info for this endpoint. In the form [{where}, {name}, {sample}]
     * Example: ["queryParameters", "api_key", "njiuyiw97865rfyvgfvb1"]
     */
    public array $auth = [];

    public ?ReflectionClass $controller;

    public ?\ReflectionFunctionAbstract $method;

    public ?Route $route;

    public function __construct(array $parameters = [])
    {
        $parameters['uri'] = $this->normalizeResourceParamName($parameters['uri'], $parameters['route']);
        $parameters['metadata'] = $parameters['metadata'] ?? new Metadata([]);
        $parameters['responses'] = $parameters['responses'] ?? new ResponseCollection([]);

        parent::__construct($parameters);
    }

    public static function fromRoute(Route $route, array $extras = []): self
    {
        $httpMethods = self::getMethods($route);
        $uri = $route->uri();

        [$controllerName, $methodName] = u::getRouteClassAndMethodNames($route);
        $controller = new ReflectionClass($controllerName);
        $method = u::getReflectedRouteMethod([$controllerName, $methodName]);

        $data = compact('httpMethods', 'uri', 'controller', 'method', 'route');
        $data = array_merge($data, $extras);

        return new ExtractedEndpointData($data);
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

    public function name()
    {
        return sprintf("[%s] {$this->route->uri}.", implode(',', $this->route->methods));
    }

    public function endpointId()
    {
        return $this->httpMethods[0] . str_replace(['/', '?', '{', '}', ':'], '-', $this->uri);
    }

    public function normalizeResourceParamName(string $uri, Route $route): string
    {
        $params = [];
        preg_match_all('#\{(\w+?)}#', $uri, $params);

        $foundResourceParam = false;
        foreach ($params[1] as $param) {
            $pluralParam = Str::plural($param);
            $resourceRouteNames = ["$pluralParam.show", "$pluralParam.update", "$pluralParam.destroy"];

            if (Str::contains($route->action['as'] ?? '', $resourceRouteNames)) {
                $search = sprintf("%s/{%s}", $pluralParam, $param);
                if (!$foundResourceParam) {
                    // Only the first resource param should be {id}
                    $replace = "$pluralParam/{id}";
                    $foundResourceParam = true;
                } else {
                    // Subsequent ones should be {<param>_id}
                    $replace = sprintf("%s/{%s}", $pluralParam, $param.'_id');
                }
                $uri = str_replace($search, $replace, $uri);
            }
        }

        return $uri;
    }

    /**
     * Prepare the endpoint data for serialising.
     */
    public function forSerialisation()
    {
        $this->metadata = $this->metadata->except('groupName', 'groupDescription');
        return $this->except(
            // Get rid of all duplicate data
            'cleanQueryParameters', 'cleanUrlParameters', 'fileParameters', 'cleanBodyParameters',
            // and objects used only in extraction
            'route', 'controller', 'method', 'auth',
        );
    }
}