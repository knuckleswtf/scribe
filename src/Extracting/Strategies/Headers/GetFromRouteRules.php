<?php

namespace Knuckles\Scribe\Extracting\Strategies\Headers;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

class GetFromRouteRules extends Strategy
{
    /** @var string */
    public $stage = 'headers';

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules)
    {
        return $routeRules['headers'] ?? [];
    }
}
