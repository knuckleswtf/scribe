<?php

namespace Knuckles\Scribe\Writing\OpenApiSpecGenerators;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Tools\DocumentationConfig;

abstract class OpenApiGenerator
{
    use ParamHelpers;

    public function __construct(protected DocumentationConfig $config)
    {
    }

    /**
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}> $groupedEndpoints
     * @return array
     */
    public function specContent(array $groupedEndpoints): array
    {
        return [];
    }

    /**
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}> $groupedEndpoints
     * @param OutputEndpointData $endpoint
     * @return array
     */
    public function pathSpecOperation(array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        return [];
    }

    /**
     * @param OutputEndpointData[] $endpoints
     * @param Parameter[] $urlParameters
     * @return array
     */
    public function pathSpecUrlParameters(array $endpoints, array $urlParameters): array
    {
        return [];
    }
}
