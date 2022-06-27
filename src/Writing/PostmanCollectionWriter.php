<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\Response;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Camel\Output\Parameter;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Ramsey\Uuid\Uuid;

class PostmanCollectionWriter
{
    /**
     * Postman collection schema version
     * https://schema.getpostman.com/json/collection/v2.1.0/collection.json
     */
    const VERSION = '2.1.0';

    protected DocumentationConfig $config;

    protected string $baseUrl;

    public function __construct(DocumentationConfig $config = null)
    {
        $this->config = $config ?: new DocumentationConfig(config('scribe', []));
        $this->baseUrl = $this->config->get('base_url') ?: config('app.url');
    }

    /**
     * @param array[] $groupedEndpoints
     *
     * @return array
     */
    public function generatePostmanCollection(array $groupedEndpoints): array
    {
        $collection = [
            'variable' => [
                [
                    'id' => 'baseUrl',
                    'key' => 'baseUrl',
                    'type' => 'string',
                    'name' => 'string',
                    'value' => $this->baseUrl,
                ],
            ],
            'info' => [
                'name' => $this->config->get('title') ?: config('app.name'),
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => $this->config->get('description', ''),
                'schema' => "https://schema.getpostman.com/json/collection/v" . self::VERSION . "/collection.json",
            ],
            'item' => array_map(function (array $group) {
                return [
                    'name' => $group['name'],
                    'description' => $group['description'],
                    'item' => array_map(\Closure::fromCallable([$this, 'generateEndpointItem']), $group['endpoints']),
                ];
            }, $groupedEndpoints),
            'auth' => $this->generateAuthObject(),
        ];

        return $collection;
    }

    protected function generateAuthObject(): array
    {
        if (!$this->config->get('auth.enabled')) {
            return [
                'type' => 'noauth',
            ];
        }

        switch ($this->config->get('auth.in')) {
            case "basic":
                return [
                    'type' => 'basic',
                ];
            case "bearer":
                return [
                    'type' => 'bearer',
                ];
            default:
                return [
                    'type' => 'apikey',
                    'apikey' => [
                        [
                            'key' => 'in',
                            'value' => $this->config->get('auth.in'),
                            'type' => 'string',
                        ],
                        [
                            'key' => 'key',
                            'value' => $this->config->get('auth.name'),
                            'type' => 'string',
                        ],
                    ],
                ];
        }
    }

    protected function generateEndpointItem(OutputEndpointData $endpoint): array
    {
        $method = $endpoint->httpMethods[0];

        $bodyParameters = empty($endpoint->bodyParameters) ? null : $this->getBodyData($endpoint);

        if ((in_array('PUT', $endpoint->httpMethods) || in_array('PATCH', $endpoint->httpMethods))
            && isset($bodyParameters['formdata'])) {
            $method = 'POST';
            $bodyParameters['formdata'][] = [
                'key' => '_method',
                'value' => $endpoint->httpMethods[0],
                'type' => 'text',
            ];
        }

        $endpointItem = [
            'name' => $endpoint->metadata->title !== '' ? $endpoint->metadata->title : $endpoint->uri,
            'request' => [
                'url' => $this->generateUrlObject($endpoint),
                'method' => $method,
                'header' => $this->resolveHeadersForEndpoint($endpoint),
                'body' => $bodyParameters,
                'description' => $endpoint->metadata->description,
            ],
            'response' => $this->getResponses($endpoint),
        ];


        if ($endpoint->metadata->authenticated === false) {
            $endpointItem['request']['auth'] = ['type' => 'noauth'];
        }

        return $endpointItem;
    }

    protected function getBodyData(OutputEndpointData $endpoint): array
    {
        $body = [];
        $contentType = $endpoint->headers['Content-Type'] ?? null;
        switch ($contentType) {
            case 'multipart/form-data':
                $inputMode = 'formdata';
                break;
            case 'application/x-www-form-urlencoded':
                $inputMode = 'urlencoded';
                break;
            case 'application/json':
            default:
                $inputMode = 'raw';
        }
        $body['mode'] = $inputMode;
        $body[$inputMode] = [];

        switch ($inputMode) {
            case 'formdata':
            case 'urlencoded':
                $body[$inputMode] = $this->getFormDataParams(
                    $endpoint->cleanBodyParameters, null, $endpoint->bodyParameters
                );
                foreach ($endpoint->fileParameters as $key => $value) {
                    while (is_array($value)) {
                        $keys = array_keys($value);
                        if ($keys[0] === 0) {
                            // List of files
                            $key .= '[]';
                            $value = $value[0];
                        } else {
                            $key .= '['.$keys[0].']';
                            $value = $value[$keys[0]];
                        }
                    }
                    $params = [
                        'key' => $key,
                        'src' => [],
                        'type' => 'file',
                    ];
                    $body[$inputMode][] = $params;
                }
                break;
            case 'raw':
            default:
                $body[$inputMode] = json_encode($endpoint->cleanBodyParameters, JSON_UNESCAPED_UNICODE);
        }
        return $body;
    }

    /**
     * Format form-data parameters correctly for arrays eg. data[item][index] = value
     */
    protected function getFormDataParams(array $paramsKeyValue, ?string $key = null, array $paramsFullDetails = []): array
    {
        $body = [];

        foreach ($paramsKeyValue as $index => $value) {
            $index = $key ? ($key . '[' . $index . ']') : $index;

            if (!is_array($value)) {
                $body[] = [
                    'key' => $index,
                    'value' => $value,
                    'type' => 'text',
                    'description' => $paramsFullDetails[$index]->description ?? '',
                ];

                continue;
            }

            $body = array_merge($body, $this->getFormDataParams($value, $index));
        }

        return $body;
    }

    protected function resolveHeadersForEndpoint(OutputEndpointData $endpointData): array
    {
        [$where, $authParam] = $this->getAuthParamToExclude();

        $headers = collect($endpointData->headers);
        if ($where === 'header') {
            unset($headers[$authParam]);
        }

        $headers = $headers
            ->union([
                'Accept' => 'application/json',
            ])
            ->map(function ($value, $header) {
                // Allow users to write ['header' => '@{{value}}'] in config
                // and have it rendered properly as {{value}} in the Postman collection.
                $value = str_replace('@{{', '{{', $value);
                return [
                    'key' => $header,
                    'value' => $value,
                ];
            })
            ->values()
            ->all();

        return $headers;
    }

    protected function generateUrlObject(OutputEndpointData $endpointData): array
    {
        $base = [
            'host' => '{{baseUrl}}',
            // Change laravel/symfony URL params ({example}) to Postman style, prefixed with a colon
            'path' => preg_replace_callback('/\{(\w+)\??}/', function ($matches) {
                return ':' . $matches[1];
            }, $endpointData->uri),
        ];

        $query = [];
        [$where, $authParam] = $this->getAuthParamToExclude();
        /**
         * @var string $name
         * @var Parameter $parameterData
         */
        foreach ($endpointData->queryParameters as $name => $parameterData) {
            if ($where === 'query' && $authParam === $name) {
                continue;
            }

            if (Str::endsWith($parameterData->type, '[]') || $parameterData->type === 'object') {
                $values = empty($parameterData->example) ? [] : $parameterData->example;
                foreach ($values as $index => $value) {
                    // PHP's parse_str supports array query parameters as filters[0]=name&filters[1]=age OR filters[]=name&filters[]=age
                    // Going with the first to also support object query parameters
                    // See https://www.php.net/manual/en/function.parse-str.php
                    $query[] = [
                        'key' => urlencode("{$name}[$index]"),
                        'value' => urlencode($value),
                        'description' => strip_tags($parameterData->description),
                        // Default query params to disabled if they aren't required and have empty values
                        'disabled' => !$parameterData->required && empty($parameterData->example),
                    ];
                }
            } else {
                $query[] = [
                    'key' => urlencode($name),
                    'value' => $parameterData->example != null ? urlencode($parameterData->example) : '',
                    'description' => strip_tags($parameterData->description),
                    // Default query params to disabled if they aren't required and have empty values
                    'disabled' => !$parameterData->required && empty($parameterData->example),
                ];
            }
        }

        $base['query'] = $query;

        // Create raw url-parameter (Insomnia uses this on import)
        $queryString = collect($base['query'])->map(function ($queryParamData) {
            return $queryParamData['key'] . '=' . $queryParamData['value'];
        })->implode('&');
        $base['raw'] = sprintf('%s/%s%s', $base['host'], $base['path'], $queryString ? "?{$queryString}" : null);

        $urlParams = collect($endpointData->urlParameters);
        if ($urlParams->isEmpty()) {
            return $base;
        }

        $base['variable'] = $urlParams->map(function (Parameter $parameter, $name) {
            return [
                'id' => $name,
                'key' => $name,
                'value' => urlencode($parameter->example),
                'description' => $parameter->description,
            ];
        })->values()->toArray();

        return $base;
    }

    private function getAuthParamToExclude(): array
    {
        if (!$this->config->get('auth.enabled')) {
            return [null, null];
        }

        if (in_array($this->config->get('auth.in'), ['bearer', 'basic'])) {
            return ['header', 'Authorization'];
        } else {
            return [$this->config->get('auth.in'), $this->config->get('auth.name')];
        }
    }

    private function getResponses(OutputEndpointData $endpoint): array
    {
        return collect($endpoint->responses)->map(function (Response $response) {
            $headers = [];
            foreach ($response->headers as $header => $value) {
                $headers[] = [
                    'key' => $header,
                    'value' => $value
                ];
            }

            return [
                'header' => $headers,
                'code' => $response->status,
                'body' => $response->content,
                'name' => $this->getResponseDescription($response),
            ];
        })->toArray();
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
}
