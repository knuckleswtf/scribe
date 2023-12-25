<?php

namespace Knuckles\Scribe\Config;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Serializer
{
    public static function toOldConfig(Extracting $extractingConfig, Output $outputConfig): array
    {
        return [
            '__configVersion' => 'v2',
            'theme' => $outputConfig->theme,
            'title' => $outputConfig->title,
            'description' => $outputConfig->description,
            'base_url' => Arr::first($outputConfig->baseUrls) ?? null,
            'type' => $outputConfig->type[0],
            $outputConfig->type[0] => self::translateKeys($outputConfig->type[1]),
            'try_it_out' => self::translateKeys($outputConfig->tryItOut),
            'postman' => self::translateKeys($outputConfig->postman),
            'openapi' => self::translateKeys($outputConfig->openApi),
            'intro_text' => $outputConfig->introText,
            'example_languages' => $outputConfig->exampleLanguages,
            'logo' => $outputConfig->logo,
            'last_updated' => $outputConfig->lastUpdated,
            'groups' => [
                'order' => $outputConfig->groupsOrder,
                'default' => $extractingConfig->defaultGroup,
            ],

            'examples' => [
                'faker_seed' => $extractingConfig->fakerSeedForExamples,
                'models_source' => $extractingConfig->dataSourcesForExampleModels,
            ],
            'routeMatcher' => $extractingConfig->routeMatcher,
            'database_connections_to_transact' => $extractingConfig->databaseConnectionsToTransact,
            'fractal' => [
                'serializer' => $extractingConfig->fractalSerializer,
            ],
            'auth' => self::translateKeys($extractingConfig->auth),
            'strategies' => $extractingConfig->strategies,
            'routes' => static::generateRoutesConfig($extractingConfig->routes),
        ];
    }

    protected static function generateRoutesConfig(Routes $routesConfig): array
    {
        return [
            [
                'match' => [
                    'prefixes' => $routesConfig->prefixes,
                    'domains' => $routesConfig->domains,
                    'versions' => $routesConfig->dingoVersions,
                ],
                'include' => $routesConfig->alwaysInclude,
                'exclude' => $routesConfig->alwaysExclude,
            ]
        ];
    }

    protected static function translateKeys($array)
    {
        return collect($array)->mapWithKeys(function ($value, $key) {
            return [Str::snake($key) => $value];
        })->toArray();
    }
}
