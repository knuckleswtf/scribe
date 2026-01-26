<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;

/**
 * A simple strategy that returns a set of static data.
 */
class StaticData extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        return $settings['data'];
    }

    public static function withSettings(
        array $only = [],
        array $except = [],
        array $data = [],
    ): array {
        return static::wrapWithSettings(
            only: $only,
            except: $except,
            otherSettings: compact(
                'data',
            )
        );
    }
}
