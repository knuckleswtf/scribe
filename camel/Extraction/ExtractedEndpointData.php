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

    public ResponseCollection $responses;

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
        return $this->httpMethods[0] . str_replace(['/', '?', '{', '}', ':', '\\', '+', '|'], '-', $this->uri);
    }

    public function normalizeResourceParamName(string $uri, Route $route): string
    {
        $params = [];
        preg_match_all('#\{(\w+?)}#', $uri, $params);

        $resourceRouteNames = [
            ".index", ".store", ".show", ".update", ".destroy",
        ];

        if (Str::endsWith($route->action['as'] ?? '', $resourceRouteNames)) {
            // Note that resource routes can be nested eg users.posts.show
            $pluralResources = explode('.', $route->action['as']);
            array_pop($pluralResources);

            $isLastResource = true;
            foreach (array_reverse($pluralResources) as $pluralResource) {
                $singularResource = Str::singular($pluralResource);
                $singularResourceParam = str_replace('-', '_', $singularResource);

                $binding = static::getFieldBindingForUrlParam($route, $singularResourceParam);

                // If there is a field binding, like /users/{user:uuid}
                if (!is_null($binding)) {
                    // If the resource was the last, replace {singularResourceParam} with {binding}
                    // otherwise, replace {singularResourceParam} with {singularResourceParam_binding}
                    $uri = self::bindUrlParam($singularResourceParam, $binding, $isLastResource, $uri);
                }

                $isLastResource = false;
            }
        }

        foreach ($params[1] as $param) {
            // For non-resource parameters, if there's a field binding, replace that too:
            if ($binding = static::getFieldBindingForUrlParam($route, $param)) {
                $search = ["{{$param}}", "{{$param}?}"];
                $replace = ["{{$param}_{$binding}}", "{{$param}_{$binding}?}"];
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
        $copy = $this->except(
        // Get rid of all duplicate data
            'cleanQueryParameters', 'cleanUrlParameters', 'fileParameters', 'cleanBodyParameters',
            // and objects used only in extraction
            'route', 'controller', 'method', 'auth',
        );
        $copy->metadata = $copy->metadata->except('groupName', 'groupDescription', 'beforeGroup', 'afterGroup');

        return $copy;
    }

    public static function getFieldBindingForUrlParam(Route $route, string $paramName, string $default = null): ?string
    {
        $binding = null;
        // Was added in Laravel 7.x
        if (method_exists($route, 'bindingFieldFor')) {
            $binding = $route->bindingFieldFor($paramName);
        }

        return $binding ?: $default;
    }

    public static function bindUrlParam(string $singularResourceParam, string $binding, bool $isLastResource, string $uri): string
    {
        $singularResource = str_replace('_', '-', $singularResourceParam);
        $pluralResource = Str::plural($singularResource);

        $search = [
            "{$pluralResource}/{{$singularResourceParam}}",
            "{$pluralResource}/{{$singularResource}}",
            "{$pluralResource}/{{$singularResourceParam}?}",
            "{$pluralResource}/{{$singularResource}?}"
        ];

        if ($isLastResource === true) {
            $replace = ["$pluralResource/{{$binding}}", "$pluralResource/{{$binding}?}"];
        } else {
            $replace = [
                "{$pluralResource}/{{$singularResource}_{$binding}}",
                "{$pluralResource}/{{$singularResourceParam}_{$binding}}",
                "{$pluralResource}/{{$singularResource}_{$binding}?}",
                "{$pluralResource}/{{$singularResourceParam}_{$binding}?}"
            ];
        }

        return str_replace($search, $replace, $uri);
    }
}
