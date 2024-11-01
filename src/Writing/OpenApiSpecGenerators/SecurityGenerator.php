<?php

namespace Knuckles\Scribe\Writing\OpenApiSpecGenerators;

use Knuckles\Camel\Output\OutputEndpointData;

class SecurityGenerator extends OpenApiGenerator
{
    public function specContent(array $groupedEndpoints): array
    {
        $isApiAuthed = $this->config->get('auth.enabled', false);
        if (!$isApiAuthed) {
            return [];
        }

        $location = $this->config->get('auth.in');
        $parameterName = $this->config->get('auth.name');
        $description = $this->config->get('auth.extra_info');
        $scheme = match ($location) {
            'query', 'header' => [
                'type' => 'apiKey',
                'name' => $parameterName,
                'in' => $location,
                'description' => $description,
            ],
            'bearer', 'basic' => [
                'type' => 'http',
                'scheme' => $location,
                'description' => $description,
            ],
            default => [],
        };

        return [
            // All security schemes must be registered in `components.securitySchemes`...
            'components' => [
                'securitySchemes' => [
                    // 'default' is an arbitrary name for the auth scheme. Can be anything, really.
                    'default' => $scheme,
                ],
            ],
            // ...and then can be applied in `security`
            'security' => [
                [
                    'default' => [],
                ],
            ],
        ];
    }

    public function pathSpecOperation(array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        if (!$endpoint->metadata->authenticated) {
            // Make sure to exclude non-auth endpoints from auth
            return [
                'security' => [],
            ];
        }
        return [];
    }
}
