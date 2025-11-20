<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

class TestOpenApi31Generator extends OpenApiGenerator
{
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        /** @var array<int, string> $permissions */
        $permissions = $endpoint->custom['permissions'];

        $pathItem['security'] = [
            ['default' => $permissions]
        ];

        return $pathItem;
    }
}
