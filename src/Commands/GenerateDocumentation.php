<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\EndpointData as ExtractedEndpointData;
use Knuckles\Camel\Output\EndpointData;
use Knuckles\Camel\Output\EndpointData as OutputEndpointData;
use Knuckles\Camel\Output\Group;
use Knuckles\Camel\Camel;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Matching\MatchedRoute;
use Knuckles\Scribe\Matching\RouteMatcherInterface;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Tools\Utils;
use Knuckles\Scribe\Tools\Utils as u;
use Knuckles\Scribe\Writing\Writer;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

class GenerateDocumentation extends Command
{
    protected $signature = "scribe:generate
                            {--force : Discard any changes you've made to the Markdown files}
                            {--no-extraction : Skip extraction of route info and just transform the Markdown files}
    ";
    
    protected $description = 'Generate API documentation from your Laravel/Dingo routes.';

    /**
     * @var DocumentationConfig
     */
    private $docConfig;

    public static $camelDir = ".scribe/endpoints";
    public static $cacheDir = ".scribe/endpoints.cache";

    /**
     * @var bool
     */
    private $shouldExtract;

    /**
     * @var bool
     */
    private $forcing;

    public function handle(RouteMatcherInterface $routeMatcher): void
    {
        $this->bootstrap();

        if ($this->forcing) {
            $routes = $routeMatcher->getRoutes($this->docConfig->get('routes'), $this->docConfig->get('router'));
            $endpoints = $this->extractEndpointsInfo($routes);
            $groupedEndpoints = Camel::groupEndpoints($endpoints);
            $this->writeEndpointsToDisk($groupedEndpoints);
        } else if ($this->shouldExtract) {
            $latestEndpointsData = [];
            $cachedEndpoints = [];

            if (is_dir(static::$camelDir) && is_dir(static::$cacheDir)) {
                $latestEndpointsData = Camel::loadEndpointsToFlatPrimitivesArray(static::$camelDir);
                $cachedEndpoints = Camel::loadEndpointsToFlatPrimitivesArray(static::$cacheDir);
            }

            $routes = $routeMatcher->getRoutes($this->docConfig->get('routes'), $this->docConfig->get('router'));
            $endpoints = $this->extractEndpointsInfo($routes, $cachedEndpoints, $latestEndpointsData);
            $groupedEndpoints = Camel::groupEndpoints($endpoints);
            $this->writeEndpointsToDisk($groupedEndpoints);
        } else {
            if (!is_dir(static::$camelDir)) {
                throw new \Exception("Can't use --no-extraction because there are no endpoints in the {static::$camelDir} directory.");
            }
            $groupedEndpoints = Camel::loadEndpointsIntoGroups(static::$camelDir);
        }

        $writer = new Writer($this->docConfig, $this->forcing);
        $writer->writeDocs($groupedEndpoints);
    }

    /**
     * @param MatchedRoute[] $matches
     * @param array $cachedEndpoints
     * @param array $latestEndpointsData
     *
     * @return array
     */
    private function extractEndpointsInfo(array $matches, array $cachedEndpoints = [], array $latestEndpointsData = []): array
    {
        $generator = new Extractor($this->docConfig);
        $parsedRoutes = [];
        foreach ($matches as $routeItem) {
            $route = $routeItem->getRoute();

            $routeControllerAndMethod = u::getRouteClassAndMethodNames($route);
            if (!$this->isValidRoute($routeControllerAndMethod)) {
                c::warn('Skipping invalid route: ' . c::getRouteRepresentation($route));
                continue;
            }

            if (!$this->doesControllerMethodExist($routeControllerAndMethod)) {
                c::warn('Skipping route: ' . c::getRouteRepresentation($route) . ' - Controller method does not exist.');
                continue;
            }

            if ($this->isRouteHiddenFromDocumentation($routeControllerAndMethod)) {
                c::warn('Skipping route: ' . c::getRouteRepresentation($route) . ': @hideFromAPIDocumentation was specified.');
                continue;
            }

            try {
                c::info('Processing route: ' . c::getRouteRepresentation($route));
                $currentEndpointData = $generator->processRoute($route, $routeItem->getRules());
                // If latest data is different from cached data, merge latest into current
                $currentEndpointData = $this->mergeAnyEndpointDataUpdates($currentEndpointData, $cachedEndpoints, $latestEndpointsData);
                $parsedRoutes[] = $currentEndpointData;
                c::success('Processed route: ' . c::getRouteRepresentation($route));
            } catch (\Exception $exception) {
                c::error('Failed processing route: ' . c::getRouteRepresentation($route) . ' - Exception encountered.');
                e::dumpExceptionIfVerbose($exception);
            }
        }

        return $parsedRoutes;
    }

    private function mergeAnyEndpointDataUpdates(ExtractedEndpointData $endpointData, array $cachedEndpoints, array $latestEndpointsData): ExtractedEndpointData
    {
        // First, find the corresponding endpoint in cached and latest
        $thisEndpointCached = Arr::first($cachedEndpoints, function ($endpoint) use ($endpointData) {
            return $endpoint['uri'] === $endpointData->uri && $endpoint['methods'] === $endpointData->methods;
        });
        if (!$thisEndpointCached) {
            return $endpointData;
        }

        $thisEndpointLatest = Arr::first($latestEndpointsData, function ($endpoint) use ($endpointData) {
            return $endpoint['uri'] === $endpointData->uri && $endpoint['methods'] == $endpointData->methods;
        });
        if (!$thisEndpointLatest) {
            return $endpointData;
        }

        // Then compare cached and latest to see what sections changed.
        $properties = [
            'metadata',
            'headers',
            'urlParameters',
            'queryParameters',
            'bodyParameters',
            'responses',
            'responseFields',
            'auth',
        ];

        $changed = [];
        foreach ($properties as $property) {
            if ($thisEndpointCached[$property] != $thisEndpointLatest[$property]) {
                $changed[] = $property;
            }
        }

        // Finally, merge any changed sections.
        foreach ($changed as $property) {
            $thisEndpointLatest = OutputEndpointData::create($thisEndpointLatest);
            $endpointData->$property = $thisEndpointLatest->$property;
        }

        return $endpointData;
    }

    private function isValidRoute(array $routeControllerAndMethod = null): bool
    {
        if (is_array($routeControllerAndMethod)) {
            [$classOrObject, $method] = $routeControllerAndMethod;
            if (u::isInvokableObject($classOrObject)) {
                return true;
            }
            $routeControllerAndMethod = $classOrObject . '@' . $method;
        }

        return !is_callable($routeControllerAndMethod) && !is_null($routeControllerAndMethod);
    }

    private function doesControllerMethodExist(array $routeControllerAndMethod): bool
    {
        [$class, $method] = $routeControllerAndMethod;
        $reflection = new ReflectionClass($class);

        if ($reflection->hasMethod($method)) {
            return true;
        }

        return false;
    }

    private function isRouteHiddenFromDocumentation(array $routeControllerAndMethod): bool
    {
        if (!($class = $routeControllerAndMethod[0]) instanceof \Closure) {
            $classDocBlock = new DocBlock((new ReflectionClass($class))->getDocComment() ?: '');
            $shouldIgnoreClass = collect($classDocBlock->getTags())
                ->filter(function (Tag $tag) {
                    return Str::lower($tag->getName()) === 'hidefromapidocumentation';
                })->isNotEmpty();

            if ($shouldIgnoreClass) {
                return true;
            }
        }

        $methodDocBlock = new DocBlock(u::getReflectedRouteMethod($routeControllerAndMethod)->getDocComment() ?: '');
        $shouldIgnoreMethod = collect($methodDocBlock->getTags())
            ->filter(function (Tag $tag) {
                return Str::lower($tag->getName()) === 'hidefromapidocumentation';
            })->isNotEmpty();

        return $shouldIgnoreMethod;
    }

    public function bootstrap(): void
    {
        // The --verbose option is included with all Artisan commands.
        Globals::$shouldBeVerbose = $this->option('verbose');

        c::bootstrapOutput($this->output);

        $this->docConfig = new DocumentationConfig(config('scribe'));

        // Force root URL so it works in Postman collection
        $baseUrl = $this->docConfig->get('base_url') ?? config('app.url');
        URL::forceRootUrl($baseUrl);

        $this->forcing = $this->option('force');
        $this->shouldExtract = !$this->option('no-extraction');

        if ($this->forcing && !$this->shouldExtract) {
            throw new \Exception("Can't use --force and --no-extraction together.");
        }
    }

    protected function writeEndpointsToDisk(array $grouped): void
    {
        Utils::deleteDirectoryAndContents(static::$camelDir);
        Utils::deleteDirectoryAndContents(static::$cacheDir);

        if (!is_dir(static::$camelDir)) {
            mkdir(static::$camelDir, 0777, true);
        }

        if (!is_dir(static::$cacheDir)) {
            mkdir(static::$cacheDir, 0777, true);
        }

        $i = 0;
        foreach ($grouped as $group) {
            $group['endpoints'] = array_map(function (EndpointData $endpoint) {
                return $endpoint->toArray();
            }, $group['endpoints']);
            $yaml = Yaml::dump(
                $group, 10, 2,
                Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            );
            file_put_contents(static::$camelDir."/$i.yaml", $yaml);
            file_put_contents(static::$cacheDir."/$i.yaml", "## Autogenerated by Scribe. DO NOT MODIFY.\n\n" . $yaml);
            $i++;
        }
    }
}
