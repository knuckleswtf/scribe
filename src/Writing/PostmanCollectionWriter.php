<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;

class PostmanCollectionWriter
{
    /**
     * Postman collection schema version
     */
    const VERSION = '2.1.0';

    /**
     * @var array|null
     */
    private $auth;

    /**
     * @var DocumentationConfig
     */
    protected $config;

    public function __construct(DocumentationConfig $config = null)
    {
        $this->config = $config ?: new DocumentationConfig(config('scribe', []));
        $this->auth = config('scribe.postman.auth');

        if ($this->auth) {
            c::deprecated('the `postman.auth` config setting', 'the `postman.overrides` setting');
        }
    }

    public function generatePostmanCollection(Collection $groupedEndpoints)
    {
        $description = config('scribe.postman.description', '');

        if ($description) {
            c::deprecated('the `postman.description` config setting', 'the `description` setting');
        } else {
            $description = config('scribe.description', '');
        }

        $collection = [
            'variable' => [],
            'info' => [
                'name' => config('scribe.title') ?: config('app.name') . ' API',
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => $description,
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

        if (!empty($this->auth)) {
            $collection['auth'] = $this->auth;
        }

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
        return [
            'name' => $endpoint['metadata']['title'] !== '' ? $endpoint['metadata']['title'] : $endpoint['uri'],
            'request' => [
                'url' => $this->generateUrlObject($endpoint),
                'method' => $endpoint['methods'][0],
                'header' => $this->resolveHeadersForEndpoint($endpoint),
                'body' => empty($endpoint['bodyParameters']) ? null : $this->getBodyData($endpoint),
                'description' => $endpoint['metadata']['description'] ?? null,
                'auth' => ($endpoint['metadata']['authenticated'] ?? false) ? null : ['type' => 'noauth'],
            ],
            'response' => [],
        ];
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
                foreach ($endpoint['cleanBodyParameters'] as $key => $value) {
                    $params = [
                        'key' => $key,
                        'value' => $value,
                        'type' => 'text',
                    ];
                    $body[$inputMode][] = $params;
                }
                foreach ($endpoint['fileParameters'] as $key => $value) {
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
                $body[$inputMode] = json_encode($endpoint['cleanBodyParameters'], JSON_PRETTY_PRINT);
        }
        return $body;
    }

    protected function resolveHeadersForEndpoint($route)
    {
        $headers = collect($route['headers']);

        // Exclude authentication headers if they're handled by Postman auth
        $authHeader = $this->getAuthHeader();
        if (!empty($authHeader)) {
            $headers = $headers->except($authHeader);
        }

        return $headers
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
    }

    protected function generateUrlObject($route)
    {
        // URL Parameters are collected by the `UrlParameters` strategies, but only make sense if they're in the route
        // definition. Filter out any URL parameters that don't appear in the URL.
        $urlParams = collect($route['urlParameters'])->filter(function ($_, $key) use ($route) {
            return Str::contains($route['uri'], '{' . $key . '}');
        });

        $baseUrl = $this->getBaseUrl($this->config->get('postman.base_url', $this->config->get('base_url')));
        $base = [
            'protocol' => Str::startsWith($baseUrl, 'https') ? 'https' : 'http',
            'host' => $baseUrl,
            // Change laravel/symfony URL params ({example}) to Postman style, prefixed with a colon
            'path' => preg_replace_callback('/\{(\w+)\??}/', function ($matches) {
                return ':' . $matches[1];
            }, $route['uri']),
            'query' => collect($route['queryParameters'] ?? [])->map(function ($parameterData, $key) {
                // TODO remove: unneeded with new syntax
                $key = rtrim($key, ".*");
                return [
                    'key' => $key,
                    'value' => urlencode($parameterData['value']),
                    'description' => strip_tags($parameterData['description']),
                    // Default query params to disabled if they aren't required and have empty values
                    'disabled' => !($parameterData['required'] ?? false) && empty($parameterData['value']),
                ];
            })->values()->toArray(),
        ];

        // Create raw url-parameter (Insomnia uses this on import)
        $query = collect($base['query'] ?? [])->map(function ($queryParamData) {
            return $queryParamData['key'] . '=' . $queryParamData['value'];
        })->implode('&');
        $base['raw'] = sprintf('%s://%s/%s%s',
            $base['protocol'], $base['host'], $base['path'], $query ? '?' . $query : null
        );

        // If there aren't any url parameters described then return what we've got
        /** @var $urlParams Collection */
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

    protected function getAuthHeader()
    {
        $auth = $this->auth;
        if (empty($auth) || !is_string($auth['type'] ?? null)) {
            return null;
        }

        switch ($auth['type']) {
            case 'bearer':
                return 'Authorization';
            case 'apikey':
                $spec = $auth['apikey'];

                if (isset($spec['in']) && $spec['in'] !== 'header') {
                    return null;
                }

                return $spec['key'];
            default:
                return null;
        }
    }

    protected function getBaseUrl($baseUrl)
    {
        if (Str::contains(app()->version(), 'Lumen')) { //Is Lumen
            $reflectionMethod = new ReflectionMethod(\Laravel\Lumen\Routing\UrlGenerator::class, 'getRootUrl');
            $reflectionMethod->setAccessible(true);
            $url = app('url');

            return $reflectionMethod->invokeArgs($url, ['', $baseUrl]);
        }

        return URL::formatRoot('', $baseUrl);
    }
}
