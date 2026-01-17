<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Collection;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\Base31Generator;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\BaseGenerator;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OverridesGenerator;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\SecurityGenerator;

class OpenAPISpecWriter
{
    use ParamHelpers;

    public const SPEC_VERSION = '3.0.3';

    private DocumentationConfig $config;

    /**
     * @var Collection<int, OpenApiGenerator>
     */
    private Collection $generators;

    public function __construct(?DocumentationConfig $config = null)
    {
        $this->config = $config ?: new DocumentationConfig(config('scribe', []));
        $generators = [
            $this->isOpenApi31OrLater() ? Base31Generator::class : BaseGenerator::class,
            SecurityGenerator::class,
            OverridesGenerator::class,
        ];
        $this->generators = collect($generators)
            ->merge($this->config->get('openapi.generators', []))
            ->map(fn ($generatorClass) => app()->makeWith($generatorClass, ['config' => $this->config]));
    }

    /**
     * Get the OpenAPI spec version to use from config, defaulting to 3.0.3.
     * Supported versions: '3.0.3', '3.1.0'.
     *
     * @return string The OpenAPI version
     */
    public function getSpecVersion(): string
    {
        return $this->config->get('openapi.version', self::SPEC_VERSION);
    }

    /**
     * See https://swagger.io/specification/.
     *
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     */
    public function generateSpecContent(array $groupedEndpoints): array
    {
        $paths = ['paths' => $this->generatePathsSpec($groupedEndpoints)];

        $content = [];
        foreach ($this->generators as $generator) {
            $content = $generator->root($content, $groupedEndpoints);
        }

        return array_replace_recursive($content, $paths);
    }

    /**
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     */
    protected function generatePathsSpec(array $groupedEndpoints): array
    {
        $allEndpoints = collect($groupedEndpoints)->map->endpoints->flatten(1);
        // OpenAPI groups endpoints by path, then method
        $groupedByPath = $allEndpoints->groupBy(function ($endpoint) {
            $path = str_replace('?}', '}', $endpoint->uri); // Remove optional parameters indicator in path

            return '/'.mb_ltrim($path, '/');
        });

        return $groupedByPath->mapWithKeys(function (Collection $endpoints, $path) use ($groupedEndpoints) {
            $operations = $endpoints->mapWithKeys(function (OutputEndpointData $endpoint) use ($groupedEndpoints) {
                $spec = [];

                foreach ($this->generators as $generator) {
                    $spec = $generator->pathItem($spec, $groupedEndpoints, $endpoint);
                }

                return [mb_strtolower($endpoint->httpMethods[0]) => $spec];
            });

            $pathItem = $operations;

            // Placing all URL parameters at the path level, since it's the same path anyway
            /** @var OutputEndpointData $urlParameterEndpoint */
            $urlParameterEndpoint = $endpoints[0];

            $parameters = [];

            foreach ($this->generators as $generator) {
                $parameters = $generator->pathParameters($parameters, $endpoints->all(), $urlParameterEndpoint->urlParameters);
            }
            if (! empty($parameters)) {
                $pathItem['parameters'] = array_values($parameters);
            }

            return [$path => $pathItem];
        })->toArray();
    }

    protected function isOpenApi31OrLater(): bool
    {
        $version = $this->config->get('openapi.version', self::SPEC_VERSION);

        return version_compare($version, '3.1.0', '>=');
    }
}
