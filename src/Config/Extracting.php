<?php

namespace Knuckles\Scribe\Config;

class Extracting
{
    public function __construct(
        public Routes  $routes,
        public string  $defaultGroup = 'Endpoints',
        public array   $databaseConnectionsToTransact = [],
        public ?int    $fakerSeedForExamples = null,
        public array   $dataSourcesForExampleModels = ['factoryCreate', 'factoryMake', 'databaseFirst'],
        public ?string $routeMatcher = null,
        public ?string $fractalSerializer = null,
        public array   $auth = [],
        public array   $strategies = [],
    )
    {
    }

    public static function auth(
        bool   $enabled = true,
        bool   $default = false,
        string $in = 'bearer',
        string $name = 'key',
        ?string $useValue = null,
        string $placeholder = '{YOUR_AUTH_KEY}',
        string $extraInfo = ''
    ): array
    {
        return get_defined_vars();
    }

    public static function strategies(
        StrategyListWrapper $metadata,
        StrategyListWrapper $urlParameters,
        StrategyListWrapper $queryParameters,
        StrategyListWrapper $headers,
        StrategyListWrapper $bodyParameters,
        StrategyListWrapper $responses,
        StrategyListWrapper $responseFields,
    ): array
    {
        return array_map(fn($listWrapper) => $listWrapper->toArray(), get_defined_vars());
    }

    public static function with(
        Routes $routes,
        string $defaultGroup = 'Endpoints',
        array $databaseConnectionsToTransact = [],
        ?int $fakerSeedForExamples = null,
        array $dataSourcesForExampleModels = ['factoryCreate', 'factoryMake', 'databaseFirst'],
        ?string $routeMatcher = null,
        ?string $fractalSerializer = null,
        array   $auth = [],
        array   $strategies = [],
    ): Extracting
    {
        return new self(...get_defined_vars());
    }
}
