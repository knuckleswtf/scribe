<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Knuckles\Camel\Camel;
use Knuckles\Camel\Extraction\Response;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Utils;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\BaseGenerator;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\SecurityGenerator;
use function array_map;

class OpenAPISpecWriter
{
    use ParamHelpers;

    const SPEC_VERSION = '3.0.3';

    private DocumentationConfig $config;

    /**
     * @var Collection<int, OpenApiGenerator>
     */
    private Collection $generators;

    public function __construct(DocumentationConfig $config = null)
    {
        $this->config = $config ?: new DocumentationConfig(config('scribe', []));
        $this->generators = collect([
                BaseGenerator::class,
                SecurityGenerator::class,
            ])
            ->merge($this->config->get('openapi.generators',[]))
            ->map(fn($generatorClass) => app()->makeWith($generatorClass, ['config' => $this->config]));
    }

    /**
     * See https://swagger.io/specification/
     *
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}> $groupedEndpoints
     *
     * @return array
     */
    public function generateSpecContent(array $groupedEndpoints): array
    {
        $paths = ['paths' => $this->generatePathsSpec($groupedEndpoints)];

        $content = [];
        foreach ($this->generators as $generator) {
            $content = array_merge($content, $generator->specContent($groupedEndpoints));
        }

        return array_merge($content, $paths);
    }

    /**
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     *
     * @return array
     */
    protected function generatePathsSpec(array $groupedEndpoints): array
    {
        $allEndpoints = collect($groupedEndpoints)->map->endpoints->flatten(1);
        // OpenAPI groups endpoints by path, then method
        $groupedByPath = $allEndpoints->groupBy(function ($endpoint) {
            $path = str_replace("?}", "}", $endpoint->uri); // Remove optional parameters indicator in path
            return '/' . ltrim($path, '/');
        });
        return $groupedByPath->mapWithKeys(function (Collection $endpoints, $path) use ($groupedEndpoints) {
            $operations = $endpoints->mapWithKeys(function (OutputEndpointData $endpoint) use ($groupedEndpoints) {
                $spec = [];

                foreach ($this->generators as $generator) {
                    $spec = array_merge($spec, $generator->pathSpecOperation($groupedEndpoints, $endpoint));
                }

                return [strtolower($endpoint->httpMethods[0]) => $spec];
            });

            $pathItem = $operations;

            // Placing all URL parameters at the path level, since it's the same path anyway
            if (count($endpoints[0]->urlParameters)) {
                /** @var OutputEndpointData $urlParameterEndpoint */
                $urlParameterEndpoint = $endpoints[0];

                $parameters = [];

                foreach ($this->generators as $generator) {
                    $parameters = array_merge($parameters, $generator->pathSpecUrlParameters($endpoints->all(), $urlParameterEndpoint->urlParameters));
                }

                $pathItem['parameters'] = $parameters;
            }

            return [$path => $pathItem];
        })->toArray();
    }
}
