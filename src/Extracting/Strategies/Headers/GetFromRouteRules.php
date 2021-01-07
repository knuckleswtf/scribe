<?php

namespace Knuckles\Scribe\Extracting\Strategies\Headers;

use Knuckles\Camel\Extraction\EndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

class GetFromRouteRules extends Strategy
{
    public string $stage = 'headers';

    public function __invoke(EndpointData $endpointData, array $routeRules)
    {
        return $routeRules['headers'] ?? [];
    }
}
