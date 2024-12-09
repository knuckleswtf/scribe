<?php

namespace Knuckles\Scribe\Writing\OpenApiSpecGenerators;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Tools\DocumentationConfig;

/**
 * Class used to generate OpenAPI spec.
 *
 * This class is responsible for generating the OpenAPI spec for your API. For additional customization, you can extend
 * this class and override the methods.
 * Each method corresponds to a different part of the OpenAPI spec. The return value of each method will set the value,
 * so if you want to extend on what other generators have done, add to the array and return it.
 */
abstract class OpenApiGenerator
{
    use ParamHelpers;

    public function __construct(protected DocumentationConfig $config)
    {
    }

    /**
     * This section is the root of the OpenAPI document. It contains general info about the API.
     *
     * @param array $root
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}> $groupedEndpoints
     * @return array
     * @see https://spec.openapis.org/oas/v3.1.1.html#openapi-object
     */
    public function root(array $root, array $groupedEndpoints): array
    {
        return $root;
    }

    /**
     * This section is the individual path item object in an OpenApi document. It contains the details of the specific
     * endpoint. This will be called for each individual endpoint, e.g. post, get, put, delete, etc.
     *
     * @param array $pathItem
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}> $groupedEndpoints
     * @param OutputEndpointData $endpoint
     * @return array
     * @see https://spec.openapis.org/oas/v3.1.1.html#path-item-object
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        return $pathItem;
    }

    /**
     * This section of the spec is the parameters object inside the path item object. It contains the details of all the
     * parameters for the endpoints matching the path. This will be called for each individual path, e.g. /users, /posts
     * it will not be called if a path has multiple endpoints, e.g. get and post.
     *
     * @param array $parameters
     * @param OutputEndpointData[] $endpoints
     * @param Parameter[] $urlParameters
     * @return array
     * @see https://spec.openapis.org/oas/v3.1.1.html#parameter-object
     */
    public function pathParameters(array $parameters, array $endpoints, array $urlParameters): array
    {
        return $parameters;
    }
}
