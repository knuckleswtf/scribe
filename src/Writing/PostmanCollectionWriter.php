<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;

class PostmanCollectionWriter
{
    /**
     * Postman collection schema version
     */
    const VERSION = '2.1.0';

    /**
     * @var DocumentationConfig
     */
    protected $config;

    protected $baseUrl;

    public function __construct(DocumentationConfig $config = null)
    {
        $this->config = $config ?: new DocumentationConfig(config('scribe', []));
        $this->baseUrl = ($this->config->get('postman.base_url') ?: $this->config->get('base_url')) ?: config('app.url');
    }

    public function generatePostmanCollection(Collection $groupedEndpoints)
    {
        $collection = [
            'variable' => [
                [
                    'id' => 'baseUrl',
                    'key' => 'baseUrl',
                    'type' => 'string',
                    'name' => 'string',
                    'value' => parse_url($this->baseUrl, PHP_URL_HOST) ?: $this->baseUrl, // if there's no protocol, parse_url might fail
                ],
            ],
            'info' => [
                'name' => $this->config->get('title') ?: config('app.name'),
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => $this->config->get('description', ''),
                'schema' => "https://schema.getpostman.com/json/collection/v" . self::VERSION . "/collection.json",
            ],
            'item' => $groupedEndpoints->map(function (Collection $routes, $groupName) {
                return [
                    'name' => $groupName,
                    'description' => $routes->first()['metadata']['groupDescription'],
                    'item' => $routes->map(\Closure::fromCallable([$this, 'generateEndpointItem']))->toArray(),
                ];
            })->values()->toArray(),
            'auth' => $this->generateAuthObject(),
        ];
        return $collection;
    }

    protected function generateAuthObject()
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

    protected function generateEndpointItem($endpoint): array
    {
        $endpointItem = [
            'name' => $endpoint['metadata']['title'] !== '' ? $endpoint['metadata']['title'] : $endpoint['uri'],
            'request' => [
                'url' => $this->generateUrlObject($endpoint),
                'method' => $endpoint['methods'][0],
                'header' => $this->resolveHeadersForEndpoint($endpoint),
                'body' => empty($endpoint['bodyParameters']) ? null : $this->getBodyData($endpoint),
                'description' => $endpoint['metadata']['description'] ?? null,
            ],
            'response' => [],
        ];


        if (($endpoint['metadata']['authenticated'] ?? false) === false) {
            $endpointItem['request']['auth'] = ['type' => 'noauth'];
        }
		
        foreach ($endpoint['responses'] as $index => $response) {
			$endpointItem['response'][] = [
				'name'            => $endpointItem['name'] . ' Response #' . ($index + 1),
				'originalRequest' => $endpointItem['request'],
				'header'          => null,
				'cookie'          => [],
				'body'            => json_encode(json_decode($response['content']), JSON_PRETTY_PRINT),
			];
		}

        return $endpointItem;
    }

    protected function getBodyData(array $endpoint): array
    {
        $body = [];
        $contentType = $endpoint['headers']['Content-Type'] ?? null;
        switch ($contentType) {
            case 'multipart/form-data':
                $inputMode = 'formdata';
                break;
            case 'application/json':
            default:
                $inputMode = 'raw';
        }
        $body['mode'] = $inputMode;
        $body[$inputMode] = [];

        switch ($inputMode) {
            case 'formdata':
                $body[$inputMode] = $this->getFormDataParams($endpoint['cleanBodyParameters']);
                foreach ($endpoint['fileParameters'] as $key => $value) {
                    while (is_array($value)) { // For arrays of files, just send the first one
                        $key .= '[]';
                        $value = $value[0];
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
                $body[$inputMode] = json_encode($endpoint['cleanBodyParameters'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return $body;
    }

    /**
	 * This formats the Form Paramaters correctly for Arrays eg. data[item][index] = value
	 * @param array $array
	 * @param string|null $key
	 * @return array
	 */
	protected function getFormDataParams(array $array, ?string $key = null): array
	{
		$body = [];

		foreach ($array as $index => $value) {
			$index = $key ? ($key . '[' . $index . ']') : $index;

			if (!is_array($value)) {
				$body[] = [
					'key'   => $index,
					'value' => $value,
					'type'  => 'text',
				];

				continue;
			}

			$body = array_merge($body, $this->getFormDataParams($value, $index));
		}

		return $body;
	}

    protected function resolveHeadersForEndpoint($route)
    {
        [$where, $authParam] = $this->getAuthParamToExclude();

        $headers = collect($route['headers']);
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

    protected function generateUrlObject($route)
    {
        $base = [
            'protocol' => Str::startsWith($this->baseUrl, 'https') ? 'https' : 'http',
            'host' => '{{baseUrl}}',
            // Change laravel/symfony URL params ({example}) to Postman style, prefixed with a colon
            'path' => preg_replace_callback('/\{(\w+)\??}/', function ($matches) {
                return ':' . $matches[1];
            }, $route['uri']),
        ];

        $query = [];
        [$where, $authParam] = $this->getAuthParamToExclude();
        foreach ($route['queryParameters'] ?? [] as $name => $parameterData) {
            if ($where === 'query' && $authParam === $name) {
                continue;
            }

            if (Str::endsWith($parameterData['type'], '[]') || $parameterData['type'] === 'object') {
                $values = empty($parameterData['value']) ? [] : $parameterData['value'];
                foreach ($values as $index => $value) {
                    // PHP's parse_str supports array query parameters as filters[0]=name&filters[1]=age OR filters[]=name&filters[]=age
                    // Going with the first to also support object query parameters
                    // See https://www.php.net/manual/en/function.parse-str.php
                    $query[] = [
                        'key' => urlencode("{$name}[$index]"),
                        'value' => urlencode($value),
                        'description' => strip_tags($parameterData['description']),
                        // Default query params to disabled if they aren't required and have empty values
                        'disabled' => !($parameterData['required'] ?? false) && empty($parameterData['value']),
                    ];
                }
            } else {
                $query[] = [
                    'key' => urlencode($name),
                    'value' => urlencode($parameterData['value']),
                    'description' => strip_tags($parameterData['description']),
                    // Default query params to disabled if they aren't required and have empty values
                    'disabled' => !($parameterData['required'] ?? false) && empty($parameterData['value']),
                ];
            }
        }

        $base['query'] = $query;

        // Create raw url-parameter (Insomnia uses this on import)
        $queryString = collect($base['query'] ?? [])->map(function ($queryParamData) {
            return $queryParamData['key'] . '=' . $queryParamData['value'];
        })->implode('&');
        $base['raw'] = sprintf('%s://%s/%s%s',
            $base['protocol'], $base['host'], $base['path'], $queryString ? "?{$queryString}" : null
        );

        $urlParams = collect($route['urlParameters']);
        if ($urlParams->isEmpty()) {
            return $base;
        }

        $base['variable'] = $urlParams->map(function ($parameter, $name) {
            return [
                'id' => $name,
                'key' => $name,
                'value' => urlencode($parameter['value']),
                'description' => $parameter['description'],
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
}
