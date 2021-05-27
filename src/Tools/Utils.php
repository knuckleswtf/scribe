<?php

namespace Knuckles\Scribe\Tools;

use Closure;
use Exception;
use FastRoute\RouteParser\Std;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;

class Utils
{
    public static function getUrlWithBoundParameters(Route $route, array $urlParameters = []): string
    {
        $uri = $route->uri();

        return self::replaceUrlParameterPlaceholdersWithValues($uri, $urlParameters);
    }

    /**
     * Transform parameters in URLs into real values (/users/{user} -> /users/2).
     * Uses @urlParam values specified by caller, otherwise just uses '1'.
     *
     * @param string $uri
     * @param array $urlParameters Dictionary of url params and example values
     *
     * @return mixed
     */
    public static function replaceUrlParameterPlaceholdersWithValues(string $uri, array $urlParameters)
    {
        if (empty($urlParameters)) {
            return $uri;
        }

        if (self::isLumen()) {
            $boundUri = '';
            $possibilities = (new Std)->parse($uri);
            // See https://github.com/nikic/FastRoute#overriding-the-route-parser-and-dispatcher
            $possibilityWithAllSegmentsPresent = end($possibilities);
            foreach ($possibilityWithAllSegmentsPresent as $part) {
                if (!is_array($part)) {
                    // It's just a path segment, not a URL parameter'
                    $boundUri .= $part;
                    continue;
                }

                $name = $part[0];
                $boundUri .= $urlParameters[$name];
            }

            return $boundUri;
        }

        foreach ($urlParameters as $parameterName => $example) {
            $uri = preg_replace('#\{' . $parameterName . '\??}#', $example, $uri);
        }

        // Remove unbound optional parameters with nothing
        $uri = preg_replace('#{([^/]+\?)}#', '', $uri);
        // Replace any unbound non-optional parameters with '1'
        $uri = preg_replace('#{([^/]+)}#', '1', $uri);

        return $uri;
    }

    public static function getRouteClassAndMethodNames(Route $route): array
    {
        $action = $route->getAction();

        $uses = $action['uses'];

        if ($uses !== null) {
            if (is_array($uses)) {
                return $uses;
            } elseif (is_string($uses)) {
                return explode('@', $uses);
            } elseif (static::isInvokableObject($uses)) {
                return [$uses, '__invoke'];
            }
        }
        if (array_key_exists(0, $action) && array_key_exists(1, $action)) {
            return [
                0 => $action[0],
                1 => $action[1],
            ];
        }

        throw new Exception("Couldn't get class and method names for route " . c::getRouteRepresentation($route) . '.');
    }

    public static function deleteDirectoryAndContents($dir, $base = null)
    {
        $adapter = new Local($base ?: getcwd());
        $fs = new Filesystem($adapter);
        $dir = str_replace($adapter->getPathPrefix(), '', $dir);
        $fs->deleteDir($dir);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function isInvokableObject($value): bool
    {
        return is_object($value) && method_exists($value, '__invoke');
    }

    /**
     * Returns the route method or closure as an instance of ReflectionMethod or ReflectionFunction
     *
     * @param array $routeControllerAndMethod
     *
     * @return ReflectionFunctionAbstract
     * @throws ReflectionException
     *
     */
    public static function getReflectedRouteMethod(array $routeControllerAndMethod): ReflectionFunctionAbstract
    {
        [$class, $method] = $routeControllerAndMethod;

        if ($class instanceof Closure) {
            return new ReflectionFunction($class);
        }

        return (new ReflectionClass($class))->getMethod($method);
    }

    public static function isArrayType(string $typeName)
    {
        return Str::endsWith($typeName, '[]');
    }

    public static function getBaseTypeFromArrayType(string $typeName)
    {
        return substr($typeName, 0, -2);
    }

    public static function getModelFactory(string $modelName, array $states = [])
    {
        if (method_exists($modelName, 'factory')) { // Laravel 8 type factory
            $factory = call_user_func_array([$modelName, 'factory'], []);
            if (count($states)) {
                foreach ($states as $state) {
                    $factory = $factory->$state();
                }
            }
        } else {
            $factory = factory($modelName);
            if (count($states)) {
                $factory = $factory->states($states);
            }
        }

        return $factory;
    }

    public static function isLumen(): bool
    {
        // See https://github.com/laravel/lumen-framework/blob/99330e6ca2198e228f5894cf84d843c2a539a250/src/Application.php#L163
        $app = app();
        if ($app
            && is_callable([$app, 'version'])
            && Str::startsWith($app->version(), 'Lumen')
        ) {
            return true;
        }

        return false;
    }

    public static function arrayMapRecursive($arr, $fn)
    {
        return array_map(function ($item) use ($fn) {
            return is_array($item) ? self::arrayMapRecursive($item, $fn) : $fn($item);
        }, $arr);
    }

}
