<?php

namespace Knuckles\Scribe\Tools;

use Closure;
use DirectoryIterator;
use Exception;
use FastRoute\RouteParser\Std;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Knuckles\Scribe\Exceptions\CouldntFindFactory;
use Knuckles\Scribe\Exceptions\CouldntGetRouteDetails;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use Throwable;

class Utils
{
    /**
     * Sometimes you have a config array that can have items as keys or values, like this:
     *
     * [
     *   'a',
     *   'b' => [ (options for b) ]
     * ]
     *
     * This method extracts the top-level options (['a', 'b'])
     *
     * @param array $mixedList
     */
    public static function getTopLevelItemsFromMixedConfigList(array $mixedList): array
    {
        $topLevels = [];
        foreach ($mixedList as $item => $value) {
            $topLevels[] = is_int($item) ? $value : $item;
        }
        return $topLevels;
    }

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
                $usesArray = explode('@', $uses);
                if (count($usesArray) < 2) {
                    throw CouldntGetRouteDetails::new();
                }
                [$class, $method] = $usesArray;

                // Support for the Laravel Actions package, docblock should be put on the asController method
                if ($method === '__invoke' && method_exists($class, 'asController'))
                {
                    return [$class, 'asController'];
                }

                return [$class, $method];
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
        if (class_exists(LocalFilesystemAdapter::class)) {
            // Flysystem 2+
            $workingDir ??= getcwd();
            $adapter = new LocalFilesystemAdapter($workingDir);
            $fs = new Filesystem($adapter);
            $dir = str_replace($workingDir, '', $dir);
            $fs->deleteDirectory($dir);
        } else {
            // v1
            $adapter = new \League\Flysystem\Adapter\Local($workingDir ?: getcwd());
            $fs = new Filesystem($adapter);
            $dir = str_replace($adapter->getPathPrefix(), '', $dir);
            $fs->deleteDir($dir);
        }
    }

    public static function listDirectoryContents(string $dir)
    {
        if (class_exists(LocalFilesystemAdapter::class)) {
            // Flysystem 2+
            $adapter = new LocalFilesystemAdapter(getcwd());
            $fs = new Filesystem($adapter);
            return $fs->listContents($dir);
        } else {
            // v1
            $adapter = new \League\Flysystem\Adapter\Local(getcwd()); // @phpstan-ignore-line
            $fs = new Filesystem($adapter); // @phpstan-ignore-line
            $dir = str_replace($adapter->getPathPrefix(), '', $dir); // @phpstan-ignore-line
            return $fs->listContents($dir);
        }
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
        if (class_exists(LocalFilesystemAdapter::class)) {
            // Flysystem 2+
            $adapter = new LocalFilesystemAdapter(getcwd());
            $fs = new Filesystem($adapter);
            $contents = $fs->listContents(ltrim($dir, '/'));
        } else {
            // v1
            $adapter = new \League\Flysystem\Adapter\Local(getcwd()); // @phpstan-ignore-line
            $fs = new Filesystem($adapter); // @phpstan-ignore-line
            $dir = str_replace($adapter->getPathPrefix(), '', $dir); // @phpstan-ignore-line
            $contents = $fs->listContents(ltrim($dir, '/'));
        }
        foreach ($contents as $file) {
            // Flysystem v1 had items as arrays; v2 has objects.
            // v2 allows ArrayAccess, but when we drop v1 support (Laravel <9), we should switch to methods
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
        if (count($routeControllerAndMethod) < 2) {
            throw CouldntGetRouteDetails::new();
        }
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

    /**
     * @param string $modelName
     * @param string[] $states
     * @param string[] $relations
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     * @throws \Throwable
     */
    public static function getModelFactory(string $modelName, array $states = [], array $relations = [])
    {
        // Factories are usually defined without the leading \ in the class name,
        // but the user might write it that way in a comment. Let's be safe.
        $modelName = ltrim($modelName, '\\');

        if (method_exists($modelName, 'factory')) { // Laravel 8 type factory
            /** @var \Illuminate\Database\Eloquent\Factories\Factory $factory */
            $factory = call_user_func_array([$modelName, 'factory'], []);
            foreach ($states as $state) {
                if (method_exists(get_class($factory), $state)) {
                    $factory = $factory->$state();
                }
            }

            foreach ($relations as $relation) {
                // Support nested relations; see https://github.com/knuckleswtf/scribe/pull/364 for a detailed example
                // Example: App\Models\Author with=posts.categories
                $relationChain = explode('.', $relation);
                $relationVector = array_shift($relationChain);

                $relation = (new $modelName())->{$relationVector}();
                $relationType = get_class($relation);
                $relationModel = get_class($relation->getModel());

                $factoryChain = empty($relationChain)
                    ? call_user_func_array([$relationModel, 'factory'], [])
                    : Utils::getModelFactory($relationModel, $states, [implode('.', $relationChain)]);

                if ($relation instanceof BelongsToMany) {
                    $pivot = method_exists($factory, 'pivot' . $relationVector)
                        ? $factory->{'pivot' . $relationVector}()
                        : [];

                    $factory = $factory->hasAttached($factoryChain, $pivot, $relationVector);
                } else if ($relationType === BelongsTo::class) {
                    $factory = $factory->for($factoryChain, $relationVector);
                } else {
                    $factory = $factory->has($factoryChain, $relationVector);
                }
            }
        } else {
            try {
                $factory = factory($modelName);
            } catch (Throwable $e) {
                if (Str::contains($e->getMessage(), "Call to undefined function Knuckles\\Scribe\\Tools\\factory()")) {
                    throw CouldntFindFactory::forModel($modelName);
                } else {
                    throw $e;
                }
            }
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

    /**
     * Filter a list of docblock tags to those matching the specified ones (case-insensitive).
     *
     * @param Tag[] $tags
     * @param string ...$names
     *
     * @return Tag[]
     */
    public static function filterDocBlockTags(array $tags, string ...$names): array
    {
        // Avoid "holes" in the keys of the filtered array by using array_values
        return array_values(
            array_filter($tags, fn($tag) => in_array(strtolower($tag->getName()),$names))
        );
    }

    /**
     * Like Laravel's trans/__ function, but will fallback to using the default translation if translation fails.
     * For instance, if the user's locale is DE, but they have no DE strings defined,
     * Laravel simply renders the translation key.
     * Instead, we render the EN version.
     */
    public static function trans(string $key, array $replace = [])
    {
        // We only load our custom translation layer if we really need it
        if (!ScribeServiceProvider::$customTranslationLayerLoaded) {
            (new ScribeServiceProvider(app()))->loadCustomTranslationLayer();
        }

        $translation = trans($key, $replace);

        if ($translation === $key || $translation === null) {
            $translation = trans($key, $replace, 'en');
        }


        if ($translation === $key) {
            throw new \Exception("Translation not found for $key. You can add a translation for this in your `lang/scribe.php`, but this is likely a problem with the package. Please open an issue.");
        }

        return $translation;
    }
}

function getTopLevelItemsFromMixedOrderList(array $mixedList): array
{
  $topLevels = [];
  foreach ($mixedList as $item => $value) {
    $topLevels[] = is_int($item) ? $value : $item;
  }
  return $topLevels;
}
