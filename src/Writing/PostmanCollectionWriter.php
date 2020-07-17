<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;

class PostmanCollectionWriter
{
    /**
     * @var Collection
     */
    private $routeGroups;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $protocol;

    /**
     * @var array|null
     */
    private $auth;

    /**
     * CollectionWriter constructor.
     *
     * @param Collection $routeGroups
     */
    public function __construct(Collection $routeGroups, $baseUrl)
    {
        $this->routeGroups = $routeGroups;
        $this->protocol = Str::startsWith($baseUrl, 'https') ? 'https' : 'http';
        $this->baseUrl = $this->getBaseUrl($baseUrl);
        $this->auth = config('scribe.postman.auth');
    }

    public function makePostmanCollection()
    {
        $collection = [
            'variables' => [],
            'info' => [
                'name' => config('scribe.title') ?: config('app.name') . ' API',
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => config('scribe.postman.description') ?: '',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
            ],
            'item' => $this->routeGroups->map(function (Collection $routes, $groupName) {
                return [
                    'name' => $groupName,
                    'description' => $routes->first()['metadata']['groupDescription'],
                    'item' => $routes->map(\Closure::fromCallable([$this, 'generateEndpointItem']))->toArray(),
                ];
            })->values()->toArray(),
        ];

        if (! empty($this->auth)) {
            $collection['auth'] = $this->auth;
        }

        return json_encode($collection, JSON_PRETTY_PRINT);
    }

    protected function generateEndpointItem($route): array
    {
        $method = $route['methods'][0];

        return [
            'name' => $route['metadata']['title'] !== '' ? $route['metadata']['title'] : $route['uri'],
            'request' => [
                'url' => $this->makeUrlData($route),
                'method' => $method,
                'header' => $this->resolveHeadersForRoute($route),
                'body' => $this->getBodyData($route),
                'description' => $route['metadata']['description'] ?? null,
                'response' => [],
            ],
        ];
    }

    protected function getBodyData(array $route): array
    {

        $body = [];
        $contentType = $route['headers']['Content-Type'] ?? null;
        switch ($contentType) {
            case 'multipart/form-data':
                $mode = 'formdata';
                break;
            case 'application/json':
            default:
                $mode = 'raw';
        }
        $body['mode'] = $mode;

        switch ($mode) {
            case 'formdata':
                foreach ($route['cleanBodyParameters'] as $key => $value) {
                    $params = [
                        'key' => $key,
                        'value' => $value,
                        'type' => 'text'
                    ];
                    $body[$mode][] = $params;
                }
                foreach ($route['fileParameters'] as $key => $value) {
                    $params = [
                        'key' => $key,
                        'src' => [],
                        'type' => 'file'
                    ];
                    $body[$mode][] = $params;
                }
                break;
            case 'raw':
            default:
                $body[$mode] = json_encode($route['cleanBodyParameters'], JSON_PRETTY_PRINT);
                $body['options'][$mode]['language'] = 'json';
        }
        return $body;
    }


    protected function resolveHeadersForRoute($route)
    {
        $headers = collect($route['headers']);

        // Exclude authentication headers if they're handled by Postman auth
        $authHeader = $this->getAuthHeader();
        if (! empty($authHeader)) {
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

    protected function makeUrlData($route)
    {
        // URL Parameters are collected by the `UrlParameters` strategies, but only make sense if they're in the route
        // definition. Filter out any URL parameters that don't appear in the URL.
        $urlParams = collect($route['urlParameters'])->filter(function ($_, $key) use ($route) {
            return Str::contains($route['uri'], '{' . $key . '}');
        });

        $base = [
            'protocol' => $this->protocol,
            'host' => $this->baseUrl,
            // Substitute laravel/symfony query params ({example}) to Postman style, prefixed with a colon
            'path' => preg_replace_callback('/\/{(\w+)\??}(?=\/|$)/', function ($matches) {
                return '/:' . $matches[1];
            }, $route['uri']),
            'query' => collect($route['queryParameters'] ?? [])->map(function ($parameterData, $key) {
                $key = rtrim($key,".*");
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
            return $queryParamData['key'].'='.$queryParamData['value'];
        })->implode('&');
        $base['raw'] = sprintf('%s://%s/%s%s',
            $base['protocol'], $base['host'], $base['path'], $query ? '?'.$query : null
        );

        // If there aren't any url parameters described then return what we've got
        /** @var $urlParams Collection */
        if ($urlParams->isEmpty()) {
            return $base;
        }

        $base['variable'] = $urlParams->map(function ($parameter, $key) {
            return [
                'id' => $key,
                'key' => $key,
                'value' => urlencode($parameter['value']),
                'description' => $parameter['description'],
            ];
        })->values()->toArray();

        return $base;
    }

    protected function getAuthHeader()
    {
        $auth = $this->auth;
        if (empty($auth) || ! is_string($auth['type'] ?? null)) {
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
