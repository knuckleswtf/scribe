<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Knuckles\Camel\Camel;
use Knuckles\Camel\Extraction\Response;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Utils;
use function array_map;

class OpenAPISpecWriter
{
    use ParamHelpers;

    const SPEC_VERSION = '3.0.3';

    private DocumentationConfig $config;

    /**
     * Object to represent empty values, since empty arrays get serialised as objects.
     * Can't use a constant because of initialisation expression.
     *
     */
    public \stdClass $EMPTY;

    public function __construct(DocumentationConfig $config = null)
    {
        $this->config = $config ?: new DocumentationConfig(config('scribe', []));
        $this->EMPTY = new \stdClass();
    }

    /**
     * See https://swagger.io/specification/
     *
     * @param array[] $groupedEndpoints
     *
     * @return array
     */
    public function generateSpecContent(array $groupedEndpoints): array
    {
        return array_merge([
            'openapi' => self::SPEC_VERSION,
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
            'paths' => $this->generatePathsSpec($groupedEndpoints),
            'tags' => array_values(array_map(function (array $group) {
                return [
                    'name' => $group['name'],
                    'description' => $group['description'],
                ];
            }, $groupedEndpoints)),
        ], $this->generateSecurityPartialSpec());
    }

    /**
     * @param array[] $groupedEndpoints
     *
     * @return mixed
     */
    protected function generatePathsSpec(array $groupedEndpoints)
    {
        $allEndpoints = collect($groupedEndpoints)->map->endpoints->flatten(1);
        // OpenAPI groups endpoints by path, then method
        $groupedByPath = $allEndpoints->groupBy(function ($endpoint) {
            $path = str_replace("?}", "}", $endpoint->uri); // Remove optional parameters indicator in path
            return '/' . ltrim($path, '/');
        });
        return $groupedByPath->mapWithKeys(function (Collection $endpoints, $path) use ($groupedEndpoints) {
            $operations = $endpoints->mapWithKeys(function (OutputEndpointData $endpoint) use ($groupedEndpoints) {
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

                if (!$endpoint->metadata->authenticated) {
                    // Make sure to exclude non-auth endpoints from auth
                    $spec['security'] = [];
                }

                return [strtolower($endpoint->httpMethods[0]) => $spec];
            });

            $pathItem = $operations;

            // Placing all URL parameters at the path level, since it's the same path anyway
            if (count($endpoints[0]->urlParameters)) {
                $parameters = [];
                /**
                 * @var string $name
                 * @var Parameter $details
                 */
                foreach ($endpoints[0]->urlParameters as $name => $details) {
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
                    $parameters[] = $parameterData;
                }
                $pathItem['parameters'] = $parameters; // @phpstan-ignore-line
            }

            return [$path => $pathItem];
        })->toArray();
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
                if (in_array($name, ['Content-Type', 'content-type', 'Accept', 'accept']))
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

    protected function generateEndpointRequestBodySpec(OutputEndpointData $endpoint)
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
        return count($body) > 0 ? $body : $this->EMPTY;
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
            } else {
                $responses[$response->status] = [
                    'description' => $this->getResponseDescription($response),
                    'content' => $this->generateResponseContentSpec($response->content, $endpoint),
                ];
            }
        }

        // return object rather than empty array, so can get properly serialised as object
        return count($responses) > 0 ? $responses : $this->EMPTY;
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
                    return [$key => $this->generateSchemaForValue($value, $endpoint, $key)];
                })->toArray();

                if (!count($properties)) {
                    $properties = $this->EMPTY;
                }

                return [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'example' => $decoded,
                            'properties' => $properties,
                        ],
                    ],
                ];
        }
    }

    protected function generateSecurityPartialSpec(): array
    {
        $isApiAuthed = $this->config->get('auth.enabled', false);
        if (!$isApiAuthed) {
            return [];
        }

        $location = $this->config->get('auth.in');
        $parameterName = $this->config->get('auth.name');

        $scheme = match ($location) {
            'query', 'header' => [
                'type' => 'apiKey',
                'name' => $parameterName,
                'in' => $location,
                'description' => '',
            ],
            'bearer', 'basic' => [
                'type' => 'http',
                'scheme' => $location,
                'description' => '',
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

    protected function convertScribeOrPHPTypeToOpenAPIType($type)
    {
        return match ($type) {
            'float', 'double' => 'number',
            'NULL' => 'string',
            default => $type,
        };
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
            ];
        } else if (Utils::isArrayType($field->type)) {
            $baseType = Utils::getBaseTypeFromArrayType($field->type);
            $baseItem = ($baseType === 'file') ? [
                'type' => 'string',
                'format' => 'binary',
            ] : ['type' => $baseType];

            $fieldData = [
                'type' => 'array',
                'description' => $field->description ?: '',
                'example' => $field->example,
                'items' => Utils::isArrayType($baseType)
                    ? $this->generateFieldData([
                        'name' => '',
                        'type' => $baseType,
                        'example' => ($field->example ?: [null])[0],
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
            return [
                'type' => 'object',
                'description' => $field->description ?: '',
                'example' => $field->example,
                'properties' => collect($field->__fields)->mapWithKeys(function ($subfield, $subfieldName) {
                    return [$subfieldName => $this->generateFieldData($subfield)];
                })->all(),
            ];
        } else {
            return [
                'type' => static::normalizeTypeName($field->type),
                'description' => $field->description ?: '',
                'example' => $field->example,
            ];
        }
    }

    protected function operationId(OutputEndpointData $endpoint): string
    {
        if ($endpoint->metadata->title) return preg_replace('/[^\w+]/', '', Str::camel($endpoint->metadata->title));

        $parts = preg_split('/[^\w+]/', $endpoint->uri, -1, PREG_SPLIT_NO_EMPTY);
        return Str::lower($endpoint->httpMethods[0]) . join('', array_map(fn ($part) => ucfirst($part), $parts));
    }

    /**
     * Given a value, generate the schema for it. The schema consists of: {type:, example:, properties: (if value is an object)},
     * and possibly a description for each property.
     * The $endpoint and $path are used for looking up response field descriptions.
     */
    public function generateSchemaForValue(mixed $value, OutputEndpointData $endpoint, string $path): array
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
            $schema = [
                'type' => 'object',
                'properties' => [],
            ];
            // Recurse into the object
            foreach($value as $subField => $subValue){
                $subFieldPath = sprintf('%s.%s', $path, $subField);
                $schema['properties'][$subField] = $this->generateSchemaForValue($subValue, $endpoint, $subFieldPath);
            }

            return $schema;
        }

        $schema = [
            'type' => $this->convertScribeOrPHPTypeToOpenAPIType(gettype($value)),
            'example' => $value,
        ];
        if (isset($endpoint->responseFields[$path]->description)) {
            $schema['description'] = $endpoint->responseFields[$path]->description;
        }

        if ($schema['type'] === 'array' && !empty($value)) {
            $schema['example'] = json_decode(json_encode($schema['example']), true); // Convert stdClass to array

            $sample = $value[0];
            $typeOfEachItem = $this->convertScribeOrPHPTypeToOpenAPIType(gettype($sample));
            $schema['items']['type'] = $typeOfEachItem;

            if ($typeOfEachItem === 'object') {
                $schema['items']['properties'] = collect($sample)->mapWithKeys(function ($v, $k) use ($endpoint, $path) {
                    return [$k => $this->generateSchemaForValue($v, $endpoint, "$path.$k")];
                })->toArray();
            }
        }

        return $schema;
    }
}
