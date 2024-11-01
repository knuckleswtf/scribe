<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

class TestOpenApiGenerator extends OpenApiGenerator
{
    public function pathSpecOperation(array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        /** @var array<int, string> $permissions */
        $permissions = $endpoint->custom['permissions'];

        return [
            'security' => [
                ['default' => $permissions]
            ],
        ];
    }
}
