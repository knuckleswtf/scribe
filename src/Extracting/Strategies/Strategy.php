<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

abstract class Strategy
{
    public ?ExtractedEndpointData $endpointData;

    public function __construct(protected DocumentationConfig $config)
    {
    }

    /**
     * Returns an instance of the documentation config
     *
     * @return DocumentationConfig
     */
    public function getConfig(): DocumentationConfig
    {
        return $this->config;
    }

    /**
     * @param ExtractedEndpointData $endpointData
     * @param array $settings Settings to be applied to this strategy while processing this route.
     *   In the past, this was "routeRules".
     *
     * @return array|null
     */
    abstract public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array;

    /**
     * @param array $only The routes which this strategy should be applied to. Can not be specified with $except.
     *   Specify route names ("users.index", "users.*"), or method and path ("GET *", "POST /safe/*").
     * @param array $except The routes which this strategy should be applied to. Can not be specified with $only.
     *   Specify route names ("users.index", "users.*"), or method and path ("GET *", "POST /safe/*").
     * @return array{string,array} Tuple of strategy class FQN and specified settings.
     */
    public static function wrapWithSettings(
        array $only = ['*'],
        array $except = [],
        ...$otherSettings
    ): array
    {
        if (!empty($only) && !empty($except)) {
            throw new \InvalidArgumentException(
                "You can not specify both \$only and \$except together in your ".static::class." settings"
            );
        }

        return [
            static::class,
            // This would be ...$otherSettings, but it's PHP 8.1+
            array_merge(['only' => $only, 'except' => $except], $otherSettings),
        ];
    }
}
