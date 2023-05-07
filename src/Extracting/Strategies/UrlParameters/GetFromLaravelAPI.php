<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Shared\UrlParamsNormalizer;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\Utils;
use Throwable;

class GetFromLaravelAPI extends Strategy
{
    use ParamHelpers;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        if (Utils::isLumen()) return null;

        $parameters = [];

        $path = $endpointData->uri;
        preg_match_all('/\{(.*?)\}/', $path, $matches);

        foreach ($matches[1] as $match) {
            $isOptional = Str::endsWith($match, '?');
            $name = rtrim($match, '?');

            $parameters[$name] = [
                'name' => $name,
                'description' => $this->inferUrlParamDescription($endpointData->uri, $name),
                'required' => !$isOptional,
            ];
        }

        $parameters = $this->inferBetterTypesAndExamplesForEloquentUrlParameters($parameters, $endpointData);

        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            $parameters = $this->inferBetterTypesAndExamplesForEnumUrlParameters($parameters, $endpointData);
        }

        $parameters = $this->setTypesAndExamplesForOthers($parameters, $endpointData);

        return $parameters;
    }

    protected function inferUrlParamDescription(string $url, string $paramName): string
    {
        // If $url is sth like /users/{id}, return "The ID of the user."
        // If $url is sth like /anything/{user_id}, return "The ID of the user."

        $strategies = collect(["id", "slug"])->map(function ($name) {
            $friendlyName = $name === 'id' ? "ID" : $name;

            return function ($url, $paramName) use ($name, $friendlyName) {
                if ($paramName == $name) {
                    $thing = $this->getNameOfUrlThing($url, $paramName);
                    return "The $friendlyName of the $thing.";
                } else if (Str::is("*_$name", $paramName)) {
                    $thing = str_replace(["_", "-"], " ", str_replace("_$name", '', $paramName));
                    return "The $friendlyName of the $thing.";
                }
            };
        })->toArray();

        // If $url is sth like /categories/{category}, return "The category."
        $strategies[] = function ($url, $paramName) {
            $thing = $this->getNameOfUrlThing($url, $paramName);
            if ($thing === $paramName) {
                return "The $thing.";
            }
        };

        foreach ($strategies as $strategy) {
            if ($inferred = $strategy($url, $paramName)) {
                return $inferred;
            }
        }

        return '';
    }

    protected function inferBetterTypesAndExamplesForEloquentUrlParameters(array $parameters, ExtractedEndpointData $endpointData): array
    {
        //We'll gather Eloquent model instances that can be linked to a URl parameter
        $modelInstances = [];

        // First, any bound models
        // Eg if route is /users/{id}, and (User $user) model is typehinted on method
        // If User model has `id` as an integer, then {id} param should be an integer
        $typeHintedEloquentModels = UrlParamsNormalizer::getTypeHintedEloquentModels($endpointData->method);
        foreach ($typeHintedEloquentModels as $argumentName => $modelInstance) {
            $routeKey = $modelInstance->getRouteKeyName();

            // Find the param name. In our normalized URL, argument $user might be param {user}, or {user_id}, or {id},
            if (isset($parameters[$argumentName])) {
                $paramName = $argumentName;
            } else if (isset($parameters["{$argumentName}_$routeKey"])) {
                $paramName = "{$argumentName}_$routeKey";
            } else if (isset($parameters[$routeKey])) {
                $paramName = $routeKey;
            } else {
                continue;
            }

            $modelInstances[$paramName] = $modelInstance;
        }

        // Next, non-Eloquent-bound parameters. They might still be Eloquent models, but model binding wasn't used.
        foreach ($parameters as $name => $data) {
            if (isset($data['type'])) continue;

            // If the url is /things/{id}, try to find a Thing model
            $urlThing = $this->getNameOfUrlThing($endpointData->uri, $name);
            if ($urlThing && ($modelInstance = $this->findModelFromUrlThing($urlThing))) {
                $modelInstances[$name] = $modelInstance;
            }
        }

        // Now infer.
        foreach ($modelInstances as $paramName => $modelInstance) {
            // If the routeKey is the same as the primary key in the database, use the PK's type.
            $routeKey = $modelInstance->getRouteKeyName();
            $type = $modelInstance->getKeyName() === $routeKey
                ? static::normalizeTypeName($modelInstance->getKeyType()) : 'string';

            $parameters[$paramName]['type'] = $type;

            try {
                $parameters[$paramName]['example'] = $modelInstance::first()->$routeKey ?? null;
            } catch (Throwable) {
                $parameters[$paramName]['example'] = null;
            }

        }
        return $parameters;
    }

    protected function inferBetterTypesAndExamplesForEnumUrlParameters(array $parameters, ExtractedEndpointData $endpointData): array
    {
        $typeHintedEnums = UrlParamsNormalizer::getTypeHintedEnums($endpointData->method);
        foreach ($typeHintedEnums as $argumentName => $enum) {
            $parameters[$argumentName]['type'] = static::normalizeTypeName($enum->getBackingType());

            try {
                $parameters[$argumentName]['example'] = $enum->getCases()[0]->getBackingValue();
            } catch (Throwable) {
                $parameters[$argumentName]['example'] = null;
            }
        }

        return $parameters;
    }

    protected function setTypesAndExamplesForOthers(array $parameters, ExtractedEndpointData $endpointData): array
    {
        foreach ($parameters as $name => $parameter) {
            if (empty($parameter['type'])) {
                $parameters[$name]['type'] = "string";
            }

            if (($parameter['example'] ?? null) === null) {
                // If the user explicitly set a `where()` constraint, use that to refine examples
                $parameterRegex = $endpointData->route->wheres[$name] ?? null;
                $parameters[$name]['example'] = $parameterRegex
                    ? $this->castToType($this->getFaker()->regexify($parameterRegex), $parameters[$name]['type'])
                    : $this->generateDummyValue($parameters[$name]['type'], hints: ['name' => $name]);
            }
        }
        return $parameters;
    }

    /**
     * Given a URL parameter $paramName, extract the "thing" that comes before it. eg::
     * - /<whatever>/things/{paramName} -> "thing"
     * - animals/cats/{id} -> "cat"
     * - users/{user_id}/contracts -> "user"
     *
     * @param string $url
     * @param string $paramName
     * @param string|null $alternateParamName A second paramName to try, if the original paramName isn't in the URL.
     *
     * @return string|null
     */
    protected function getNameOfUrlThing(string $url, string $paramName, string $alternateParamName = null): ?string
    {
        $parts = explode("/", $url);
        if (count($parts) === 1) return null; // URL was "/{thing}"

        $paramIndex = array_search("{{$paramName}}", $parts);

        if ($paramIndex === false) {
            $paramIndex = array_search("{{$alternateParamName}}", $parts);
        }

        if ($paramIndex === false || $paramIndex === 0) return null;

        $things = $parts[$paramIndex - 1];
        // Replace underscores/hyphens, so "side_projects" becomes "side project"
        return str_replace(["_", "-"], " ", Str::singular($things));
    }

    /**
     * Given a URL "thing", like the "cat" in /cats/{id}, try to locate a Cat model.
     *
     * @param string $urlThing
     *
     * @return Model|null
     */
    protected function findModelFromUrlThing(string $urlThing): ?Model
    {
        $className = str_replace(['-', '_', ' '], '', Str::title($urlThing));
        $rootNamespace = app()->getNamespace();

        if (class_exists($class = "{$rootNamespace}Models\\" . $className, autoload: false)
            // For the heathens that don't use a Models\ directory
            || class_exists($class = $rootNamespace . $className, autoload: false)) {
            try {
                $instance = new $class;
            } catch (\Error) { // It might be an enum or some other non-instantiable class
                return null;
            }
            return $instance instanceof Model ? $instance : null;
        }

        return null;
    }
}
