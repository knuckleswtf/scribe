<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

class TestCustomEndpointMetadata extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $endpointData->metadata->custom['myProperty'] = 'some custom metadata';

        return null;
    }
}
