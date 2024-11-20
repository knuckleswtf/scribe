<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Support\Arr;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

class ComponentsOpenApiGenerator extends OpenApiGenerator
{
    public function root(array $root, array $groupedEndpoints): array
    {
        $parameters = Arr::get($root, 'components.parameters', []);
        $parameters = array_merge($parameters, [
            'slugParam' => [
                'in' => 'path',
                'name' => 'slug',
                'description' => 'The slug of the organization.',
                'example' => 'acme-corp',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                ],
            ],
        ]);
        $root['components']['parameters'] = $parameters;

        return $root;
    }

    public function pathParameters(array $parameters, array $endpoints, array $urlParameters): array
    {
        $parameters['slug'] = ['$ref' =>  "#/components/parameters/slugParam"];

        return $parameters;
    }
}
