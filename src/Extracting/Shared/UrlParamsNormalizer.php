<?php

namespace Knuckles\Scribe\Extracting\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionEnum;
use ReflectionException;
use ReflectionFunctionAbstract;

/*
 * See https://laravel.com/docs/9.x/routing#route-model-binding
 */
class UrlParamsNormalizer
{
    /**
     * Normalize a URL from Laravel-style to something that's clearer for a non-Laravel user.
     * For instance, `/posts/{post}` would be clearer as `/posts/{id}`,
     * and `/users/{user}/posts/{post}` would be clearer as `/users/{user_id}/posts/{id}`
     *
     * @param \Illuminate\Routing\Route $route
     * @param \ReflectionFunctionAbstract $method
     *
     * @return string
     */
    public static function normalizeParameterNamesInRouteUri(Route $route, ReflectionFunctionAbstract $method): string
    {
        $params = [];
        $uri = $route->uri;
        preg_match_all('#\{(\w+?)}#', $uri, $params);

        $resourceRouteNames = [".index", ".show", ".update", ".destroy", ".store"];

        $typeHintedEloquentModels = self::getTypeHintedEloquentModels($method);
        $routeName = $route->action['as'] ?? '';
        if (Str::endsWith($routeName, $resourceRouteNames)) {
            // Note that resource routes can be nested eg users.posts.show
            $pluralResources = explode('.', $routeName);
            array_pop($pluralResources); // Remove the name of the action (eg `show`)

            $alreadyFoundResourceParam = false;
            foreach (array_reverse($pluralResources) as $pluralResource) {
                $singularResource = Str::singular($pluralResource);

                // Laravel turns hyphens in parameters to underscores
                // (`cool-things/{cool-thing}` to `cool-things/{cool_thing_id}`)
                // so we do the same
                $singularResourceParam = str_replace('-', '_', $singularResource);

                $urlPatternsToSearchFor = [
                    "{$pluralResource}/{{$singularResourceParam}}",
                    "{$pluralResource}/{{$singularResource}}",
                    "{$pluralResource}/{{$singularResourceParam}?}",
                    "{$pluralResource}/{{$singularResource}?}",
                ];

                $binding = self::getRouteKeyForUrlParam(
                    $route, $singularResource, $typeHintedEloquentModels, 'id'
                );

                if (!$alreadyFoundResourceParam) {
                    // This is the first resource param (from the end).
                    // We set it to `params/{id}` (or whatever field it's bound to)
                    $replaceWith = ["$pluralResource/{{$binding}}", "$pluralResource/{{$binding}?}"];
                    $alreadyFoundResourceParam = true;
                } else {
                    // Other resource parameters will be `params/{<param>_id}`
                    $replaceWith = [
                        "{$pluralResource}/{{$singularResourceParam}_{$binding}}",
                        "{$pluralResource}/{{$singularResource}_{$binding}}",
                        "{$pluralResource}/{{$singularResourceParam}_{$binding}?}",
                        "{$pluralResource}/{{$singularResource}_{$binding}?}",
                    ];
                }
                $uri = str_replace($urlPatternsToSearchFor, $replaceWith, $uri);
            }
        }

        foreach ($params[1] as $param) {
            // For non-resource parameters, if there's a field binding/type-hinted variable, replace that too:
            if ($binding = self::getRouteKeyForUrlParam($route, $param, $typeHintedEloquentModels)) {
                $urlPatternsToSearchFor = ["{{$param}}", "{{$param}?}"];
                $replaceWith = ["{{$param}_{$binding}}", "{{$param}_{$binding}?}"];
                $uri = str_replace($urlPatternsToSearchFor, $replaceWith, $uri);
            }
        }

        return $uri;
    }

    /**
     * Return the type-hinted method arguments in the action that are Eloquent models,
     * The arguments will be returned as an array of the form: [<variable_name> => $instance]
     */
    public static function getTypeHintedEloquentModels(ReflectionFunctionAbstract $method): array
    {
        $arguments = [];
        foreach ($method->getParameters() as $argument) {
            if (($instance = self::instantiateMethodArgument($argument)) && $instance instanceof Model) {
                $arguments[$argument->getName()] = $instance;
            }
        }

        return $arguments;
    }


    /**
     * Return the type-hinted method arguments in the action that are enums,
     * The arguments will be returned as an array of the form: [<variable_name> => $instance]
     */
    public static function getTypeHintedEnums(ReflectionFunctionAbstract $method): array
    {
        $arguments = [];
        foreach ($method->getParameters() as $argument) {
            $argumentType = $argument->getType();
            if (!($argumentType instanceof \ReflectionNamedType)) continue;
            try {
                $reflectionEnum = new ReflectionEnum($argumentType->getName());
                $arguments[$argument->getName()] = $reflectionEnum;
            } catch (ReflectionException) {
                continue;
            }
        }

        return $arguments;
    }

    /**
     * Given a URL that uses Eloquent model binding (for instance `/posts/{post}` -> `public function show(Post
     * $post)`), we need to figure out the field that Eloquent uses to retrieve the Post object. By default, this would
     * be `id`, but can be configured in a couple of ways:
     *
     * - Inline: `/posts/{post:slug}`
     * - `class Post { public function getRouteKeyName() { return 'slug'; } }`
     *
     * There are other ways, but they're dynamic and beyond our scope.
     *
     * @param \Illuminate\Routing\Route $route
     * @param string $paramName The name of the URL parameter
     * @param array<string, Model> $typeHintedEloquentModels
     * @param string|null $default Default field to use
     *
     * @return string|null
     */
    protected static function getRouteKeyForUrlParam(
        Route $route, string $paramName, array $typeHintedEloquentModels = [], string $default = null
    ): ?string
    {
        if ($binding = self::getInlineRouteKey($route, $paramName)) {
            return $binding;
        }

        return self::getRouteKeyFromModel($paramName, $typeHintedEloquentModels) ?: $default;
    }

    /**
     * Return the `slug` in /posts/{post:slug}
     *
     * @param \Illuminate\Routing\Route $route
     * @param string $paramName
     *
     * @return string|null
     */
    protected static function getInlineRouteKey(Route $route, string $paramName): ?string
    {
        // Was added in Laravel 7.x
        if (method_exists($route, 'bindingFieldFor')) {
            return $route->bindingFieldFor($paramName);
        }
        return null;
    }

    /**
     * Check if there's a type-hinted argument on the controller method matching the URL param name:
     * eg /posts/{post} -> public function show(Post $post)
     * If there is, check if it's an Eloquent model.
     * If it is, return it's `getRouteKeyName()`.
     *
     * @param string $paramName
     * @param Model[] $typeHintedEloquentModels
     *
     * @return string|null
     */
    protected static function getRouteKeyFromModel(string $paramName, array $typeHintedEloquentModels): ?string
    {
        // Ensure param name is in camelCase so it matches the argument name (e.g. The '$userAddress' in `function show(BigThing $userAddress`)
        $paramName = Str::camel($paramName);

        if (array_key_exists($paramName, $typeHintedEloquentModels)) {
            $argumentInstance = $typeHintedEloquentModels[$paramName];
            return $argumentInstance->getRouteKeyName();
        }

        return null;
    }

    /**
     * Instantiate an argument on a controller method via its typehint. For instance, $post in:
     *
     * public function show(Post $post)
     *
     * This method takes in a method argument and returns an instance, or null if it couldn't be instantiated safely.
     * Cases where instantiation may fail:
     * - the argument has no type (eg `public function show($postId)`)
     * - the argument has a primitive type (eg `public function show(string $postId)`)
     * - the argument is an injected dependency that itself needs other dependencies
     *   (eg `public function show(PostsManager $manager)`)
     *
     * @param \ReflectionParameter $argument
     *
     * @return object|null
     */
    protected static function instantiateMethodArgument(\ReflectionParameter $argument): ?object
    {
        $argumentType = $argument->getType();
        // No type-hint, or primitive type
        if (!($argumentType instanceof \ReflectionNamedType)) return null;

        $argumentClassName = $argumentType->getName();
        if (class_exists($argumentClassName)) {
            try {
                return new $argumentClassName;
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (interface_exists($argumentClassName)) {
            try {
                return app($argumentClassName);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
