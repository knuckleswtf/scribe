<?php

namespace Knuckles\Camel\Extraction;

use Illuminate\Database\Eloquent\Model;
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
        $parameters['metadata'] = $parameters['metadata'] ?? new Metadata([]);
        $parameters['responses'] = $parameters['responses'] ?? new ResponseCollection([]);

        parent::__construct($parameters);

        $this->uri = $this->normalizeResourceParamName($this->uri, $this->route, $this->getTypeHintedArguments());
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

    public function normalizeResourceParamName(string $uri, Route $route, array $typeHintedArguments): string
    {
        $params = [];
        preg_match_all('#\{(\w+?)}#', $uri, $params);

        $resourceRouteNames = [
            ".index", ".show", ".update", ".destroy",
        ];

        if (Str::endsWith($route->action['as'] ?? '', $resourceRouteNames)) {
            // Note that resource routes can be nested eg users.posts.show
            $pluralResources = explode('.', $route->action['as']);
            array_pop($pluralResources);

            $foundResourceParam = false;
            foreach (array_reverse($pluralResources) as $pluralResource) {
                $singularResource = Str::singular($pluralResource);
                $singularResourceParam = str_replace('-', '_', $singularResource);

                $search = [
                    "{$pluralResource}/{{$singularResourceParam}}",
                    "{$pluralResource}/{{$singularResource}}",
                    "{$pluralResource}/{{$singularResourceParam}?}",
                    "{$pluralResource}/{{$singularResource}?}"
                ];

                // If there is an inline binding in the route, like /users/{user:uuid}, use that key,
                // Else, search for a type-hinted variable in the action, whose name matches the route segment name,
                // If there is such variable (like User $user), call getRouteKeyName() on the model,
                // Otherwise, use the id
                $binding = static::getFieldBindingForUrlParam($route, $singularResource, $typeHintedArguments, 'id');

                if (!$foundResourceParam) {
                    // Only the last resource param should be {id}
                    $replace = ["$pluralResource/{{$binding}}", "$pluralResource/{{$binding}?}"];
                    $foundResourceParam = true;
                } else {
                    // Earlier ones should be {<param>_id}
                    $replace = [
                        "{$pluralResource}/{{$singularResource}_{$binding}}",
                        "{$pluralResource}/{{$singularResourceParam}_{$binding}}",
                        "{$pluralResource}/{{$singularResource}_{$binding}?}",
                        "{$pluralResource}/{{$singularResourceParam}_{$binding}?}"
                    ];
                }
                $uri = str_replace($search, $replace, $uri);
            }
        }

        foreach ($params[1] as $param) {
            // For non-resource parameters, if there's a field binding/type-hinted variable, replace that too:
            if ($binding = static::getFieldBindingForUrlParam($route, $param, $typeHintedArguments)) {
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

    protected static function instantiateTypedArgument(\ReflectionNamedType $argumentType): ?object
    {
        $argumentClassName = $argumentType->getName();

        if (class_exists($argumentClassName)) {
            return new $argumentClassName;
        }

        if (interface_exists($argumentClassName)) {
            return app($argumentClassName);
        }

        return null;
    }

    public static function getFieldBindingForUrlParam(
        Route $route, string $paramName, array $typeHintedArguments = [], string $default = null
    ): ?string
    {
        $binding = null;
        // Was added in Laravel 7.x
        if (method_exists($route, 'bindingFieldFor')) {
            $binding = $route->bindingFieldFor($paramName);
        }

        // Search for a type-hinted variable whose name matches the route segment name
        if (is_null($binding) && array_key_exists($paramName, $typeHintedArguments)) {
            $argumentType = $typeHintedArguments[$paramName]->getType();
            $argumentInstance = self::instantiateTypedArgument($argumentType);
            $binding = $argumentInstance instanceof Model ? $argumentInstance->getRouteKeyName() : null;
        }

        return $binding ?: $default;
    }

    /**
     * Return the type-hinted method arguments in the action that have a Model type,
     * The arguments will be returned as an array of the form: $arguments[<variable_name>] = $argument
     */
    protected function getTypeHintedArguments(): array
    {
        $arguments = [];
        if ($this->method) {
            foreach ($this->method->getParameters() as $argument) {
                if ($this->argumentHasModelType($argument)) {
                    $arguments[$argument->getName()] = $argument;
                }
            }
        }

        return $arguments;
    }

    /**
     * Determine whether the argument has a Model type
     */
    protected function argumentHasModelType(\ReflectionParameter $argument): bool
    {
        $argumentType = $argument->getType();
        if (!($argumentType instanceof \ReflectionNamedType)) {
            // The argument does not have a type-hint, or is a primitive type (`string`, ..)
            return false;
        }

        $argumentInstance = self::instantiateTypedArgument($argumentType);
        return ($argumentInstance instanceof Model);
    }
}
