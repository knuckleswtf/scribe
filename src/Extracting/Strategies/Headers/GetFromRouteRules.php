<?php

namespace Knuckles\Scribe\Extracting\Strategies\Headers;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

class GetFromRouteRules extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): array
    {
        return $routeRules['headers'] ?? [];
    }
}
