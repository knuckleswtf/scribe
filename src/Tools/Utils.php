<?php

namespace Knuckles\Scribe\Tools;

use Closure;
use DirectoryIterator;
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
    public static function getUrlWithBoundParameters(string $uri, array $urlParameters = []): string
    {
        return self::replaceUrlParameterPlaceholdersWithValues($uri, $urlParameters);
    }

    /**
     * Transform parameters in URLs into real values (/users/{user} -> /users/2).
     * Uses @urlParam values specified by caller, otherwise just uses '1'.
     *
     * @param string $uri
     * @param array $urlParameters Dictionary of url params and example values
     *
     * @return string
     */
    public static function replaceUrlParameterPlaceholdersWithValues(string $uri, array $urlParameters): string
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

    public static function deleteDirectoryAndContents(string $dir, ?string $workingDir = null): void
    {
        $adapter = new Local($workingDir ?: getcwd());
        $fs = new Filesystem($adapter);
        $dir = str_replace($adapter->getPathPrefix(), '', $dir);
        $fs->deleteDir($dir);
    }

    public static function copyDirectory(string $src, string $dest): void
    {
        if (!is_dir($src)) return;

        // If the destination directory does not exist create it
        if (!is_dir($dest)) {
            if (!mkdir($dest, 0777, true)) {
                // If the destination directory could not be created stop processing
                throw new Exception("Failed to create target directory: $dest");
            }
        }

        // Open the source directory to read in files
        $i = new DirectoryIterator($src);
        foreach ($i as $f) {
            if ($f->isFile()) {
                copy($f->getRealPath(), "$dest/" . $f->getFilename());
            } else if (!$f->isDot() && $f->isDir()) {
                self::copyDirectory($f->getRealPath(), "$dest/$f");
            }
        }
    }

    public static function deleteFilesMatching(string $dir, callable $condition): void
    {
        $adapter = new Local(getcwd());
        $fs = new Filesystem($adapter);
        $dir = ltrim($dir, '/');
        $contents = $fs->listContents($dir);
        foreach ($contents as $file) {
            if ($file['type'] == 'file' && $condition($file) === true) {
                $fs->delete($file['path']);
            }
        }
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

    public static function getModelFactory(string $modelName, array $states = [], array $relations = [])
    {
        // Factories are usually defined without the leading \ in the class name,
        // but the user might write it that way in a comment. Let's be safe.
        $modelName = ltrim($modelName, '\\');

        if (method_exists($modelName, 'factory')) { // Laravel 8 type factory
            /** @var \Illuminate\Database\Eloquent\Factories\Factory $factory */
            $factory = call_user_func_array([$modelName, 'factory'], []);
            foreach ($states as $state) {
                $factory = $factory->$state();
            }

            foreach ($relations as $relation) {
                // Eg "posts" relation becomes hasPosts() method
                $methodName = "has$relation";
                $factory = $factory->$methodName();
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

}
