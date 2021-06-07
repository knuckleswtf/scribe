<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Camel;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\ApiDetails;
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
                            {--force : Discard any changes you've made to the YAML or Markdown files}
                            {--no-extraction : Skip extraction of route and API info and just transform the YAML and Markdown files into HTML}
    ";

    protected $description = 'Generate API documentation from your Laravel/Dingo routes.';

    private DocumentationConfig $docConfig;

    public static string $camelDir = ".scribe/endpoints";
    public static string $cacheDir = ".scribe/endpoints.cache";

    private bool $shouldExtract;

    private bool $forcing;
    private bool $encounteredErrors = false;
    private array $endpointGroupIndexes = [];

    public function handle(RouteMatcherInterface $routeMatcher): void
    {
        $this->bootstrap();

        if ($this->forcing) {
            $groupedEndpoints = $this->extractEndpointsInfoAndWriteToDisk($routeMatcher, false);
            $this->extractAndWriteApiDetailsToDisk();
        } else if ($this->shouldExtract) {
            $groupedEndpoints = $this->extractEndpointsInfoAndWriteToDisk($routeMatcher, true);
            $this->extractAndWriteApiDetailsToDisk();
        } else {
            if (!is_dir(static::$camelDir)) {
                throw new \InvalidArgumentException("Can't use --no-extraction because there are no endpoints in the " . static::$camelDir . " directory.");
            }
            $groupedEndpoints = Camel::loadEndpointsIntoGroups(static::$camelDir);
        }

        $userDefinedEndpoints = Camel::loadUserDefinedEndpoints(static::$camelDir);
        $groupedEndpoints = $this->mergeUserDefinedEndpoints($groupedEndpoints, $userDefinedEndpoints);
        $writer = new Writer($this->docConfig);
        $writer->writeDocs($groupedEndpoints);

        if ($this->encounteredErrors) {
            c::warn('Generated docs, but encountered some errors while processing routes.');
            c::warn('Check the output above for details.');
        }
    }

    /**
     * @param MatchedRoute[] $matches
     * @param array $cachedEndpoints
     * @param array $latestEndpointsData
     * @param array[] $groups
     *
     * @return array
     * @throws \Exception
     */
    private function extractEndpointsInfoFromLaravelApp(array $matches, array $cachedEndpoints, array $latestEndpointsData, array $groups): array
    {
        $generator = new Extractor($this->docConfig);
        $parsedEndpoints = [];

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
                [$currentEndpointData, $index] = $this->mergeAnyEndpointDataUpdates($currentEndpointData, $cachedEndpoints, $latestEndpointsData, $groups);

                // We need to preserve order of endpoints, in case user did custom sorting
                $parsedEndpoints[] = $currentEndpointData;
                if ($index !== null) {
                    $this->endpointGroupIndexes[$currentEndpointData->endpointId()] = $index;
                }
                c::success('Processed route: ' . c::getRouteRepresentation($route));
            } catch (\Exception $exception) {
                $this->encounteredErrors = true;
                c::error('Failed processing route: ' . c::getRouteRepresentation($route) . ' - Exception encountered.');
                e::dumpExceptionIfVerbose($exception);
            }
        }

        return $parsedEndpoints;
    }

    /**
     * @param ExtractedEndpointData $endpointData
     * @param array[] $cachedEndpoints
     * @param array[] $latestEndpointsData
     * @param array[] $groups
     *
     * @return array The extracted endpoint data and the endpoint's index in the group file
     */
    private function mergeAnyEndpointDataUpdates(ExtractedEndpointData $endpointData, array $cachedEndpoints, array $latestEndpointsData, array $groups): array
    {
        // First, find the corresponding endpoint in cached and latest
        $thisEndpointCached = Arr::first($cachedEndpoints, function (array $endpoint) use ($endpointData) {
            return $endpoint['uri'] === $endpointData->uri && $endpoint['httpMethods'] === $endpointData->httpMethods;
        });
        if (!$thisEndpointCached) {
            return [$endpointData, null];
        }

        $thisEndpointLatest = Arr::first($latestEndpointsData, function (array $endpoint) use ($endpointData) {
            return $endpoint['uri'] === $endpointData->uri && $endpoint['httpMethods'] == $endpointData->httpMethods;
        });
        if (!$thisEndpointLatest) {
            return [$endpointData, null];
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
        ];

        $changed = [];
        foreach ($properties as $property) {
            if ($thisEndpointCached[$property] != $thisEndpointLatest[$property]) {
                $changed[] = $property;
            }
        }

        // Finally, merge any changed sections.
        $thisEndpointLatest = OutputEndpointData::create($thisEndpointLatest);
        foreach ($changed as $property) {
            $endpointData->$property = $thisEndpointLatest->$property;
        }
        $index = Camel::getEndpointIndexInGroup($groups, $thisEndpointLatest);

        return [$endpointData, $index];
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
        Utils::deleteFilesMatching(static::$camelDir, function (array $file) {
            return !Str::startsWith($file['basename'], 'custom.');
        });
        Utils::deleteDirectoryAndContents(static::$cacheDir);

        if (!is_dir(static::$camelDir)) {
            mkdir(static::$camelDir, 0777, true);
        }

        if (!is_dir(static::$cacheDir)) {
            mkdir(static::$cacheDir, 0777, true);
        }

        $fileNameIndex = 0;
        foreach ($grouped as $group) {
            $yaml = Yaml::dump(
                $group, 10, 2,
                Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            );
            if (count(Camel::$groupFileNames) == count($grouped)
                && isset(Camel::$groupFileNames[$group['name']])) {
                $fileName = Camel::$groupFileNames[$group['name']];
            } else {
                $fileName = "$fileNameIndex.yaml";
                $fileNameIndex++;
            }

            file_put_contents(static::$camelDir . "/$fileName", $yaml);
            file_put_contents(static::$cacheDir . "/$fileName", "## Autogenerated by Scribe. DO NOT MODIFY.\n\n" . $yaml);
        }
    }

    protected function mergeUserDefinedEndpoints(array $groupedEndpoints, array $userDefinedEndpoints): array
    {
        foreach ($userDefinedEndpoints as $endpoint) {
            $existingGroupKey = Arr::first(array_keys($groupedEndpoints), function ($key) use ($groupedEndpoints, $endpoint) {
                $group = $groupedEndpoints[$key];
                return $group['name'] === ($endpoint['metadata']['groupName'] ?? $this->docConfig->get('default_group', ''));
            });

            if ($existingGroupKey) {
                $groupedEndpoints[$existingGroupKey]['endpoints'][] = OutputEndpointData::fromExtractedEndpointArray($endpoint);
            } else {
                $groupedEndpoints[] = [
                    'name' => $endpoint['metadata']['groupName'] ?? $this->docConfig->get('default_group', ''),
                    'description' => $endpoint['metadata']['groupDescription'] ?? null,
                    'endpoints' => [OutputEndpointData::fromExtractedEndpointArray($endpoint)],
                ];
            }
        }

        return $groupedEndpoints;
    }

    protected function extractEndpointsInfoAndWriteToDisk(RouteMatcherInterface $routeMatcher, bool $preserveUserChanges): array
    {
        $latestEndpointsData = [];
        $cachedEndpoints = [];
        $groups = [];

        if ($preserveUserChanges && is_dir(static::$camelDir) && is_dir(static::$cacheDir)) {
            $latestEndpointsData = Camel::loadEndpointsToFlatPrimitivesArray(static::$camelDir);
            $cachedEndpoints = Camel::loadEndpointsToFlatPrimitivesArray(static::$cacheDir, true);
            $groups = Camel::loadEndpointsIntoGroups(static::$camelDir);
        }

        $routes = $routeMatcher->getRoutes($this->docConfig->get('routes'), $this->docConfig->get('router'));
        $endpoints = $this->extractEndpointsInfoFromLaravelApp($routes, $cachedEndpoints, $latestEndpointsData, $groups);
        $groupedEndpoints = Camel::groupEndpoints($endpoints, $this->endpointGroupIndexes);
        $this->writeEndpointsToDisk($groupedEndpoints);
        $this->writeExampleCustomEndpoint();
        $groupedEndpoints = Camel::prepareGroupedEndpointsForOutput($groupedEndpoints);
        return $groupedEndpoints;
    }

    protected function writeExampleCustomEndpoint(): void
    {
        // We add an example to guide users in case they need to add a custom endpoint.
        if (!file_exists(static::$camelDir . '/custom.0.yaml')) {
            copy(__DIR__ . '/../../resources/example_custom_endpoint.yaml', static::$camelDir . '/custom.0.yaml');
        }
    }

    protected function extractAndWriteApiDetailsToDisk(): void
    {
        $apiDetails = new ApiDetails($this->docConfig, !$this->option('force'));
        $apiDetails->writeMarkdownFiles();
    }
}
