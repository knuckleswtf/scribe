<?php

namespace Knuckles\Scribe\Writing\OpenApiSpecGenerators;

/**
 * The main generator for Open API Spec, for v3.1
 */
class Base31Generator extends BaseGenerator
{
    /**
     * Handle nullable fields based on OpenAPI version.
     * In OpenAPI 3.0, use 'nullable: true'.
     * In OpenAPI 3.1, use JSON Schema's type array syntax: 'type: ["string", "null"]'.
     */
    protected function applyNullable(array &$schema, bool $nullable): void
    {
        if (!$nullable) {
            return;
        }

        // OpenAPI 3.1 uses JSON Schema's type array syntax
        if (isset($schema['type'])) {
            $currentType = $schema['type'];
            // Don't modify if already an array
            if (!is_array($currentType)) {
                $schema['type'] = [$currentType, 'null'];
            }
        }
    }
}
