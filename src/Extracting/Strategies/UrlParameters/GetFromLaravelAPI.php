<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Illuminate\Database\Eloquent\Model;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\Utils;

class GetFromLaravelAPI extends Strategy
{
    use ParamHelpers;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        if (Utils::isLumen()) {
            return null;
        }

        $parameters = [];

        $path = $endpointData->uri;
        preg_match_all('/\{(.*?)\}/', $path, $matches);

        foreach ($matches[1] as $match) {
            $optional = Str::endsWith($match, '?');
            $name = rtrim($match, '?');

            // In case of /users/{user:id}, make the param {user_id}
            $binding = ExtractedEndpointData::getFieldBindingForUrlParam($endpointData->route, $name);
            $parameters[$name] = [
                'name' => $name,
                'description' => $this->inferUrlParamDescription($endpointData->uri, $binding ?: $name, $binding ? $name : null),
                'required' => !$optional,
            ];
        }


        // Infer proper types for any bound models
        // Eg Suppose route is /users/{user},
        // and (User $user) model is typehinted on method
        // If User model has an int primary key, {user} param should be an int

        $methodArguments = $endpointData->method->getParameters();
        foreach ($methodArguments as $argument) {
            $argumentType = $argument->getType();
            // If there's no typehint, continue
            if (!$argumentType) {
                continue;
            }
            try {
                $argumentClassName = $argumentType->getName();
                $argumentInstance = new $argumentClassName;
                if ($argumentInstance instanceof Model) {
                    if (isset($parameters[$argument->getName()])) {
                        $paramName = $argument->getName();
                    } else if (isset($parameters['id'])) {
                        $paramName = 'id';
                    } else {
                        continue;
                    }

                    // If a user customized their routeKeyName,
                    // we can't guarantee that it's the same type as the PK
                    $typeName = $argumentInstance->getKeyName() === $argumentInstance->getRouteKeyName()
                        ? $argumentInstance->getKeyType() : 'string';
                    $type = $this->normalizeTypeName($typeName);
                    $parameters[$paramName]['type'] = $type;

                    // Try to fetch an example ID from the database
                    try {
                        // todo: add some database tests
                        $example = $argumentInstance::first()->id ?? null;
                    } catch (\Throwable $e) {
                        $example = null;
                    }

                    if ($example === null) {
                        // If the user explicitly set a `where()` constraint, use that to refine examples
                        $parameterRegex = $endpointData->route->wheres[$paramName] ?? null;
                        $example = $parameterRegex
                            ? $this->castToType($this->getFaker()->regexify($parameterRegex), $type)
                            : $this->generateDummyValue($type);
                    }
                    $parameters[$paramName]['example'] = $example;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Try to infer correct types for URL parameters.
        foreach ($parameters as $name => $data) {
            if (isset($data['type'])) continue;

            $type = 'string'; // The default type

            // If the url is /things/{id}, try looking for a Thing model ourselves
            $urlThing = $this->getNameOfUrlThing($endpointData->uri, $name);
            if ($urlThing) {
                $rootNamespace = app()->getNamespace();
                if (class_exists($class = "{$rootNamespace}Models\\" . Str::title($urlThing))
                    // For the heathens that don't use a Models\ directory
                    || class_exists($class = $rootNamespace . Str::title($urlThing))) {
                    $argumentInstance = new $class;
                    $type = $this->normalizeTypeName($argumentInstance->getKeyType());
                }
            }

            $parameterRegex = $endpointData->route->wheres[$name] ?? null;
            $example = $parameterRegex
                ? $this->castToType($this->getFaker()->regexify($parameterRegex), $type)
                : $this->generateDummyValue($type);
            $parameters[$name]['example'] =$example;
            $parameters[$name]['type'] = $type;
        }

        return $parameters;
    }

    protected function inferUrlParamDescription(string $url, string $paramName, string $originalBindingName = null): string
    {
        if ($paramName == "id") {
            // If $url is sth like /users/{id} or /users/{user}, return "The ID of the user."
            // Make sure to replace underscores, so "side_projects" becomes "side project"
            $thing = str_replace(["_", "-"], " ",$this->getNameOfUrlThing($url, $paramName, $originalBindingName));
            return "The ID of the $thing.";
        } else if (Str::is("*_id", $paramName)) {
            // If $url is sth like /something/{user_id}, return "The ID of the user."
            $parts = explode("_", $paramName);
            return "The ID of the $parts[0].";
        } else if ($paramName && $originalBindingName) {
            // A case like /posts/{post:slug} -> The slug of the post
            return "The $paramName of the $originalBindingName.";
        }

        return '';
    }

    /**
     * Extract "thing" in the URL /<whatever>/things/{paramName}
     */
    protected function getNameOfUrlThing(string $url, string $paramName, string $alternateParamName = null): ?string
    {
        try {
            $parts = explode("/", $url);
            $paramIndex = array_search("{{$paramName}}", $parts);

            if ($paramIndex === false) {
                // Try with the other param name
                $paramIndex = array_search("{{$alternateParamName}}", $parts);
            }
            $things = $parts[$paramIndex - 1];
            return Str::singular($things);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
