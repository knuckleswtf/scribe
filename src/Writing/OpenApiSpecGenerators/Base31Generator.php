<?php

namespace Knuckles\Scribe\Writing\OpenApiSpecGenerators;

use Knuckles\Camel\Output\Parameter;

/**
 * The main generator for Open API Spec, for v3.1.
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
        if (! $nullable) {
            return;
        }

        // OpenAPI 3.1 uses JSON Schema's type array syntax
        if (isset($schema['type'])) {
            $currentType = $schema['type'];
            // Don't modify if already an array
            if (! is_array($currentType)) {
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
        $this->convertExampleInSchemaToExamples($fieldData);
        return $fieldData;
    }

    /**
     * Override parent's generateSchemaForResponseValue to convert 'example' to 'examples' for OpenAPI 3.1.
     */
    public function generateSchemaForResponseValue(mixed $value, \Knuckles\Camel\Output\OutputEndpointData $endpoint, string $path): array
    {
        $schema = parent::generateSchemaForResponseValue($value, $endpoint, $path);
        $this->convertExampleInSchemaToExamples($schema);
        return $schema;
    }

    /**
     * Override parent's generateResponseContentSpec to convert 'example' to 'examples' for OpenAPI 3.1.
     */
    protected function generateResponseContentSpec(?string $responseContent, \Knuckles\Camel\Output\OutputEndpointData $endpoint): array
    {
        $contentSpec = parent::generateResponseContentSpec($responseContent, $endpoint);

        // Convert example to examples in all schemas within the content spec
        foreach ($contentSpec as $contentType => &$content) {
            if (isset($content['schema'])) {
                $this->convertExampleInSchemaToExamples($content['schema']);
            }
        }

        return $contentSpec;
    }

    /**
     * Convert 'example' to 'examples' for OpenAPI 3.1 compatibility.
     * OpenAPI 3.1 uses JSON Schema, which prefers 'examples' (plural, as an array).
     */
    protected function convertExampleInSchemaToExamples(array &$schema): void
    {
        // Only convert if 'example' exists and 'examples' doesn't already exist
        // If both exist, prioritize 'examples' and remove 'example' to avoid conflicts
        if (array_key_exists('example', $schema)) {
            if (!array_key_exists('examples', $schema)) {
                $schema['examples'] = [$schema['example']];
            }
            // Remove 'example' to ensure only 'examples' is present in OpenAPI 3.1
            unset($schema['example']);
        }

        // Recursively handle nested properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as &$property) {
                $this->convertExampleInSchemaToExamples($property);
            }
        }

        // Handle items in arrays
        if (isset($schema['items']) && is_array($schema['items'])) {
            $this->convertExampleInSchemaToExamples($schema['items']);
        }
    }
    protected function convertExampleOutsideSchemaToExamples(array &$data): void
    {
        // Only convert if 'example' exists and 'examples' doesn't already exist
        // If both exist, prioritize 'examples' and remove 'example' to avoid conflicts
        if (isset($data['example'])) {
            if (!isset($data['schema']['examples'])) {
                $data['schema']['examples'] = [$data['example']];
            }
            // Remove 'example' to ensure only 'examples' is present in OpenAPI 3.1
            unset($data['example']);
        }

        // Recursively handle nested properties
        if (isset($data['schema']['properties']) && is_array($data['schema']['properties'])) {
            foreach ($data['schema']['properties'] as &$property) {
                $this->convertExampleInSchemaToExamples($property);
            }
        }

        // Handle items in arrays
        if (isset($data['schema']['items']) && is_array($data['schema']['items'])) {
            $this->convertExampleInSchemaToExamples($data['schema']['items']);
        }
    }

    protected function headerToOpenApiParameterObject(string $name, string $value): array
    {
        $data = parent::headerToOpenApiParameterObject($name, $value);
        $this->convertExampleOutsideSchemaToExamples($data);
        return $data;
    }

    protected function queryParamToOpenApiParameterObject(string $name, Parameter $details): array
    {
        $data = parent::queryParamToOpenApiParameterObject($name, $details);
        $this->convertExampleOutsideSchemaToExamples($data);
        return $data;
    }

    protected function urlParamToOpenApiParameterObject(string $name, Parameter $details): array
    {
        $data = parent::urlParamToOpenApiParameterObject($name, $details);
        if (isset($data['examples'])) {
            // This is NOT the JSON Schema 'examples' array, but the OpenAPI Parameter Object 'examples' Map, outside the schema. Leave untouched
            return $data;
        }

        $this->convertExampleOutsideSchemaToExamples($data);
        return $data;
    }
}
