<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

abstract class Strategy
{
    public ?ExtractedEndpointData $endpointData;

    public function __construct(protected DocumentationConfig $config) {}

    /**
     * @param  array  $settings  settings to be applied to this strategy
     */
    abstract public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array;

    /**
     * Returns an instance of the documentation config.
     */
    public function getConfig(): DocumentationConfig
    {
        return $this->config;
    }

    /**
     * Helper method that returns a tuple of [$strategyName, $settingsArray].
     * Main real advantage is that it validates the mutual exclusion of $only and $except.
     *
     * @param  array  $only  The routes which this strategy should be applied to. Can not be specified with $except.
     *                       Specify route names ("users.index", "users.*"), or method and path ("GET *", "POST /safe/*").
     * @param  array  $except  The routes which this strategy should be applied to. Can not be specified with $only.
     *                         Specify route names ("users.index", "users.*"), or method and path ("GET *", "POST /safe/*").
     * @return array{string,array} tuple of strategy class FQN and specified settings
     */
    public static function wrapWithSettings(
        array $only = [],
        array $except = [],
        array $otherSettings = [],
    ): array {
        return [
            static::class,
            ['only' => $only, 'except' => $except, ...$otherSettings],
        ];
    }
}
