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

    /**
     * Override parent's generateFieldData to convert 'example' to 'examples' for OpenAPI 3.1.
     * In OpenAPI 3.1, JSON Schema's 'examples' (plural, as an array) is preferred over 'example'.
     */
    public function generateFieldData($field): array
    {
        $fieldData = parent::generateFieldData($field);
        $this->convertExampleToExamples($fieldData);
        return $fieldData;
    }

    /**
     * Override parent's generateSchemaForResponseValue to convert 'example' to 'examples' for OpenAPI 3.1.
     */
    public function generateSchemaForResponseValue(mixed $value, \Knuckles\Camel\Output\OutputEndpointData $endpoint, string $path): array
    {
        $schema = parent::generateSchemaForResponseValue($value, $endpoint, $path);
        $this->convertExampleToExamples($schema);
        return $schema;
    }

    /**
     * Override parent's generateResponseContentSpec to convert 'example' to 'examples' for OpenAPI 3.1.
     */
    protected function generateResponseContentSpec(?string $responseContent, \Knuckles\Camel\Output\OutputEndpointData $endpoint)
    {
        $contentSpec = parent::generateResponseContentSpec($responseContent, $endpoint);
        
        // Convert example to examples in all schemas within the content spec
        foreach ($contentSpec as $contentType => &$content) {
            if (isset($content['schema'])) {
                $this->convertExampleToExamples($content['schema']);
            }
        }
        
        return $contentSpec;
    }

    /**
     * Convert 'example' to 'examples' for OpenAPI 3.1 compatibility.
     * OpenAPI 3.1 uses JSON Schema, which prefers 'examples' (plural, as an array).
     */
    protected function convertExampleToExamples(array &$schema): void
    {
        // Only convert if 'example' exists and 'examples' doesn't already exist
        // If both exist, prioritize 'examples' and remove 'example' to avoid conflicts
        if (isset($schema['example'])) {
            if (!isset($schema['examples'])) {
                $schema['examples'] = [$schema['example']];
            }
            // Remove 'example' to ensure only 'examples' is present in OpenAPI 3.1
            unset($schema['example']);
        }

        // Recursively handle nested properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as &$property) {
                $this->convertExampleToExamples($property);
            }
        }

        // Handle items in arrays
        if (isset($schema['items']) && is_array($schema['items'])) {
            $this->convertExampleToExamples($schema['items']);
        }
    }
}
