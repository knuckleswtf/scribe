<?php

namespace Knuckles\Scribe\Writing\OpenApiSpecGenerators;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Knuckles\Camel\Camel;
use Knuckles\Camel\Extraction\Response;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;
use Knuckles\Scribe\Tools\Utils;
use Knuckles\Scribe\Writing\OpenAPISpecWriter;

/**
 * The main generator for Open API Spec. It adds the minimum needed information to the spec.
 */
class BaseGenerator extends OpenApiGenerator
{
    public function root(array $root, array $groupedEndpoints): array
    {
        return array_merge($root, [
            'openapi' => OpenAPISpecWriter::SPEC_VERSION,
            'info' => [
                'title' => $this->config->get('title') ?: config('app.name', ''),
                'description' => $this->config->get('description', ''),
                'version' => '1.0.0',
            ],
            'servers' => [
                [
                    'url' => rtrim($this->config->get('base_url') ?? config('app.url'), '/'),
                ],
            ],
            'tags' => array_values(array_map(function (array $group) {
                return [
                    'name' => $group['name'],
                    'description' => $group['description'],
                ];
            }, $groupedEndpoints)),
        ]);
    }

    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        $spec = [
            'summary' => $endpoint->metadata->title,
            'operationId' => $this->operationId($endpoint),
            'description' => $endpoint->metadata->description,
            'parameters' => $this->generateEndpointParametersSpec($endpoint),
            'responses' => $this->generateEndpointResponsesSpec($endpoint),
            'tags' => [Arr::first($groupedEndpoints, function ($group) use ($endpoint) {
                return Camel::doesGroupContainEndpoint($group, $endpoint);
            })['name']],
        ];

        if (count($endpoint->bodyParameters)) {
            $spec['requestBody'] = $this->generateEndpointRequestBodySpec($endpoint);
        }

        return array_merge($pathItem, $spec);
    }


    public function pathParameters(array $parameters, array $endpoints, array $urlParameters): array
    {
        foreach ($urlParameters as $name => $details) {
            $parameterData = [
                'in' => 'path',
                'name' => $name,
                'description' => $details->description,
                'example' => $details->example,
                // Currently, OAS requires path parameters to be required
                'required' => true,
                'schema' => [
                    'type' => $details->type,
                ],
            ];
            // Workaround for optional parameters
            if (empty($details->required)) {
                $parameterData['description'] = rtrim('Optional parameter. ' . $parameterData['description']);
                $parameterData['examples'] = [
                    'omitted' => [
                        'summary' => 'When the value is omitted',
                        'value' => '',
                    ],
                ];

                if ($parameterData['example'] !== null) {
                    $parameterData['examples']['present'] = [
                        'summary' => 'When the value is present',
                        'value' => $parameterData['example'],
                    ];
                }

                // Can't have `example` and `examples`
                unset($parameterData['example']);
            }
            $parameters[$name] = $parameterData;
        }

        return $parameters;
    }


    protected function operationId(OutputEndpointData $endpoint): string
    {
        if ($endpoint->metadata->title) return preg_replace('/[^\w+]/', '', Str::camel($endpoint->metadata->title));

        $parts = preg_split('/[^\w+]/', $endpoint->uri, -1, PREG_SPLIT_NO_EMPTY);
        return Str::lower($endpoint->httpMethods[0]) . join('', array_map(fn($part) => ucfirst($part), $parts));
    }

    /**
     * Add query parameters and headers.
     *
     * @param OutputEndpointData $endpoint
     *
     * @return array<int, array<string,mixed>>
     */
    protected function generateEndpointParametersSpec(OutputEndpointData $endpoint): array
    {
        $parameters = [];

        if (count($endpoint->queryParameters)) {
            /**
             * @var string $name
             * @var Parameter $details
             */
            foreach ($endpoint->queryParameters as $name => $details) {
                $parameterData = [
                    'in' => 'query',
                    'name' => $name,
                    'description' => $details->description,
                    'example' => $details->example,
                    'required' => $details->required,
                    'schema' => $this->generateFieldData($details),
                ];
                $parameters[] = $parameterData;
            }
        }

        if (count($endpoint->headers)) {
            foreach ($endpoint->headers as $name => $value) {
                if (in_array(strtolower($name), ['content-type', 'accept', 'authorization']))
                    // These headers are not allowed in the spec.
                    // https://swagger.io/docs/specification/describing-parameters/#header-parameters
                    continue;

                $parameters[] = [
                    'in' => 'header',
                    'name' => $name,
                    'description' => '',
                    'example' => $value,
                    'schema' => [
                        'type' => 'string',
                    ],
                ];
            }
        }

        return $parameters;
    }

    protected function generateEndpointRequestBodySpec(OutputEndpointData $endpoint): array|\stdClass
    {
        $body = [];

        if (count($endpoint->bodyParameters)) {
            $schema = [
                'type' => 'object',
                'properties' => [],
            ];

            $hasRequiredParameter = false;
            $hasFileParameter = false;

            foreach ($endpoint->nestedBodyParameters as $name => $details) {
                if ($name === "[]") { // Request body is an array
                    $hasRequiredParameter = true;
                    $schema = $this->generateFieldData($details);
                    break;
                }

                if ($details['required']) {
                    $hasRequiredParameter = true;
                    // Don't declare this earlier.
                    // The spec doesn't allow for an empty `required` array. Must have something there.
                    $schema['required'][] = $name;
                }

                if ($details['type'] === 'file') {
                    $hasFileParameter = true;
                }

                $fieldData = $this->generateFieldData($details);

                $schema['properties'][$name] = $fieldData;
            }

            // We remove 'properties' if the request body is an array, so we need to check if it's still there
            if (array_key_exists('properties', $schema)) {
                $schema['properties'] = $this->objectIfEmpty($schema['properties']);
            }
            $body['required'] = $hasRequiredParameter;

            if ($hasFileParameter) {
                // If there are file parameters, content type changes to multipart
                $contentType = 'multipart/form-data';
            } elseif (isset($endpoint->headers['Content-Type'])) {
                $contentType = $endpoint->headers['Content-Type'];
            } else {
                $contentType = 'application/json';
            }

            $body['content'][$contentType]['schema'] = $schema;

        }

        // return object rather than empty array, so can get properly serialised as object
        return $this->objectIfEmpty($body);
    }

    protected function generateEndpointResponsesSpec(OutputEndpointData $endpoint)
    {
        // See https://swagger.io/docs/specification/describing-responses/
        $responses = [];

        foreach ($endpoint->responses as $response) {
            // OpenAPI groups responses by status code
            // Only one response type per status code, so only the last one will be used
            if (intval($response->status) === 204) {
                // Must not add content for 204
                $responses[204] = [
                    'description' => $this->getResponseDescription($response),
                ];
            } elseif (isset($responses[$response->status])) {
                // If we already have a response for this status code and content type,
                // we change to a `oneOf` which includes all the responses
                $content = $this->generateResponseContentSpec($response->content, $endpoint);
                $contentType = array_keys($content)[0];
                if (isset($responses[$response->status]['content'][$contentType])) {
                    $newResponseExample = array_replace([
                        'description' => $this->getResponseDescription($response),
                    ], $content[$contentType]['schema']);

                    // If we've already created the oneOf object, add this response
                    if (isset($responses[$response->status]['content'][$contentType]['schema']['oneOf'])) {
                        $responses[$response->status]['content'][$contentType]['schema']['oneOf'][] = $newResponseExample;
                    } else {
                        // Create the oneOf object
                        $existingResponseExample = array_replace([
                            'description' => $responses[$response->status]['description'],
                        ], $responses[$response->status]['content'][$contentType]['schema']);

                        $responses[$response->status]['description'] = '';
                        $responses[$response->status]['content'][$contentType]['schema'] = [
                            'oneOf' => [$existingResponseExample, $newResponseExample]
                        ];
                    }
                }
            } else {
                // Store as the response for this status
                $responses[$response->status] = [
                    'description' => $this->getResponseDescription($response),
                    'content' => $this->generateResponseContentSpec($response->content, $endpoint),
                ];
            }
        }

        // return object rather than empty array, so can get properly serialised as object
        return $this->objectIfEmpty($responses);
    }

    protected function getResponseDescription(Response $response): string
    {
        if (Str::startsWith($response->content, "<<binary>>")) {
            return trim(str_replace("<<binary>>", "", $response->content));
        }

        $description = strval($response->description);
        // Don't include the status code in description; see https://github.com/knuckleswtf/scribe/issues/271
        if (preg_match("/\d{3},\s+(.+)/", $description, $matches)) {
            $description = $matches[1];
        } else if ($description === strval($response->status)) {
            $description = '';
        }
        return $description;
    }

    protected function generateResponseContentSpec(?string $responseContent, OutputEndpointData $endpoint)
    {
        if (Str::startsWith($responseContent, '<<binary>>')) {
            return [
                'application/octet-stream' => [
                    'schema' => [
                        'type' => 'string',
                        'format' => 'binary',
                    ],
                ],
            ];
        }

        if ($responseContent === null) {
            return [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        // See https://swagger.io/docs/specification/data-models/data-types/#null
                        'nullable' => true,
                    ],
                ],
            ];
        }

        $decoded = json_decode($responseContent);
        if ($decoded === null) { // Decoding failed, so we return the content string as is
            return [
                'text/plain' => [
                    'schema' => [
                        'type' => 'string',
                        'example' => $responseContent,
                    ],
                ],
            ];
        }

        switch ($type = gettype($decoded)) {
            case 'string':
            case 'boolean':
            case 'integer':
            case 'double':
                return [
                    'application/json' => [
                        'schema' => [
                            'type' => $type === 'double' ? 'number' : $type,
                            'example' => $decoded,
                        ],
                    ],
                ];

            case 'array':
                if (!count($decoded)) {
                    // empty array
                    return [
                        'application/json' => [
                            'schema' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object', // No better idea what to put here
                                ],
                                'example' => $decoded,
                            ],
                        ],
                    ];
                }

                // Non-empty array
                return [
                    'application/json' => [
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => $this->convertScribeOrPHPTypeToOpenAPIType(gettype($decoded[0])),
                            ],
                            'example' => $decoded,
                        ],
                    ],
                ];

            case 'object':
                $properties = collect($decoded)->mapWithKeys(function ($value, $key) use ($endpoint) {
                    return [$key => $this->generateSchemaForResponseValue($value, $endpoint, $key)];
                })->toArray();
                $required = $this->filterRequiredResponseFields($endpoint, array_keys($properties));

                $data = [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'example' => $decoded,
                            'properties' => $this->objectIfEmpty($properties),
                        ],
                    ],
                ];
                if ($required) {
                    $data['application/json']['schema']['required'] = $required;
                }

                return $data;
            default:
                return [];
        }
    }

    /**
     * Given an array, return an object if the array is empty. To be used with fields that are
     * required by OpenAPI spec to be objects, since empty arrays get serialised as [].
     */
    protected function objectIfEmpty(array $field): array|\stdClass
    {
        return count($field) > 0 ? $field : new \stdClass();
    }


    /**
     * @param Parameter|array $field
     *
     * @return array
     */
    public function generateFieldData($field): array
    {
        if (is_array($field)) {
            $field = new Parameter($field);
        }

        if ($field->type === 'file') {
            // See https://swagger.io/docs/specification/describing-request-body/file-upload/
            return [
                'type' => 'string',
                'format' => 'binary',
                'description' => $field->description ?: '',
                'nullable' => $field->nullable,
            ];
        } else if (Utils::isArrayType($field->type)) {
            $baseType = Utils::getBaseTypeFromArrayType($field->type);
            $baseItem = ($baseType === 'file') ? [
                'type' => 'string',
                'format' => 'binary',
            ] : ['type' => $baseType];

            if (!empty($field->enumValues)) {
                $baseItem['enum'] = $field->enumValues;
            }

            if ($field->nullable) {
                $baseItem['nullable'] = true;
            }

            $fieldData = [
                'type' => 'array',
                'description' => $field->description ?: '',
                'example' => $field->example,
                'items' => Utils::isArrayType($baseType)
                    ? $this->generateFieldData([
                        'name' => '',
                        'type' => $baseType,
                        'example' => ($field->example ?: [null])[0],
                        'nullable' => $field->nullable,
                    ])
                    : $baseItem,
            ];
            if (str_replace('[]', "", $field->type) === 'file') {
                // Don't include example for file params in OAS; it's hard to translate it correctly
                unset($fieldData['example']);
            }

            if ($baseType === 'object' && !empty($field->__fields)) {
                if ($fieldData['items']['type'] === 'object') {
                    $fieldData['items']['properties'] = [];
                }
                foreach ($field->__fields as $fieldSimpleName => $subfield) {
                    $fieldData['items']['properties'][$fieldSimpleName] = $this->generateFieldData($subfield);
                    if ($subfield['required']) {
                        $fieldData['items']['required'][] = $fieldSimpleName;
                    }
                }
            }

            return $fieldData;
        } else if ($field->type === 'object') {
            $data = [
                'type' => 'object',
                'description' => $field->description ?: '',
                'example' => $field->example,
                'nullable'=> $field->nullable,
                'properties' => $this->objectIfEmpty(collect($field->__fields)->mapWithKeys(function ($subfield, $subfieldName) {
                    return [$subfieldName => $this->generateFieldData($subfield)];
                })->all()),
                'required' => collect($field->__fields)->filter(fn ($f) => $f['required'])->keys()->toArray(),
            ];
            // The spec doesn't allow for an empty `required` array. Must have something there.
            if (empty($data['required'])) {
                unset($data['required']);
            }
            return $data;
        } else {
            $schema = [
                'type' => static::normalizeTypeName($field->type),
                'description' => $field->description ?: '',
                'example' => $field->example,
                'nullable' => $field->nullable,
            ];
            if (!empty($field->enumValues)) {
                $schema['enum'] = $field->enumValues;
            }

            return $schema;
        }
    }


    /**
     * Given a value, generate the schema for it. The schema consists of: {type:, example:, properties: (if value is an
     * object)}, and possibly a description for each property. The $endpoint and $path are used for looking up response
     * field descriptions.
     */
    public function generateSchemaForResponseValue(mixed $value, OutputEndpointData $endpoint, string $path): array
    {
        if ($value instanceof \stdClass) {
            $value = (array)$value;
            $properties = [];
            // Recurse into the object
            foreach ($value as $subField => $subValue) {
                $subFieldPath = sprintf('%s.%s', $path, $subField);
                $properties[$subField] = $this->generateSchemaForResponseValue($subValue, $endpoint, $subFieldPath);
            }
            $required = $this->filterRequiredResponseFields($endpoint, array_keys($properties), $path);

            $schema = [
                'type' => 'object',
                'properties' => $this->objectIfEmpty($properties),
            ];
            if ($required) {
                $schema['required'] = $required;
            }
            $this->setDescription($schema, $endpoint, $path);

            return $schema;
        }

        $schema = [
            'type' => $this->convertScribeOrPHPTypeToOpenAPIType(gettype($value)),
            'example' => $value,
        ];
        $this->setDescription($schema, $endpoint, $path);

        // Set enum values for the property if they exist
        if (isset($endpoint->responseFields[$path]->enumValues)) {
            $schema['enum'] = $endpoint->responseFields[$path]->enumValues;
        }

        if ($schema['type'] === 'array' && !empty($value)) {
            $schema['example'] = json_decode(json_encode($schema['example']), true); // Convert stdClass to array

            $sample = $value[0];
            $typeOfEachItem = $this->convertScribeOrPHPTypeToOpenAPIType(gettype($sample));
            $schema['items']['type'] = $typeOfEachItem;

            if ($typeOfEachItem === 'object') {
                $schema['items']['properties'] = collect($sample)->mapWithKeys(function ($v, $k) use ($endpoint, $path) {
                    return [$k => $this->generateSchemaForResponseValue($v, $endpoint, "$path.$k")];
                })->toArray();
                
                $required = $this->filterRequiredResponseFields($endpoint, array_keys($schema['items']['properties']),
                    $path);
                if ($required) {
                    $schema['required'] = $required;
                }
            }
        }

        return $schema;
    }


    /**
     * Given an enpoint and a set of object keys at a path, return the properties that are specified as required.
     */
    public function filterRequiredResponseFields(OutputEndpointData $endpoint, array $properties, string $path = ''): array
    {
        $required = [];
        foreach ($properties as $property) {
            $responseField = $endpoint->responseFields["$path.$property"] ?? $endpoint->responseFields[$property] ?? null;
            if ($responseField && $responseField->required) {
                $required[] = $property;
            }
        }

        return $required;
    }

    /*
     * Set the description for the schema. If the field has a description, it is set in the schema.
     */
    private function setDescription(array &$schema, OutputEndpointData $endpoint, string $path): void
    {
        if (isset($endpoint->responseFields[$path]->description)) {
            $schema['description'] = $endpoint->responseFields[$path]->description;
        }
    }

    protected function convertScribeOrPHPTypeToOpenAPIType($type)
    {
        return match ($type) {
            'float', 'double' => 'number',
            'NULL' => 'string',
            default => $type,
        };
    }
}
