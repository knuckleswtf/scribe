<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use FastRoute\RouteParser\Std;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\Utils;

class GetFromLumenAPI extends Strategy
{
    use ParamHelpers;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        if (!Utils::isLumen()) {
            return null;
        }

        $path = $endpointData->uri;

        $parameters = [];
        $possibilities = (new Std)->parse($path);
        // See https://github.com/nikic/FastRoute#overriding-the-route-parser-and-dispatcher
        $possibilityWithAllSegmentsPresent = end($possibilities);

        foreach ($possibilityWithAllSegmentsPresent as $part) {
            if (!is_array($part)) {
                // It's just a path segment, not a URL parameter'
                continue;
            }

            $name = $part[0];
            $isThisParameterOptional = Arr::first($possibilities, function ($possibility) use ($name) {
                // This function checks if this parameter is present in the current possibility
                return (function () use ($possibility, $name) {
                        foreach ($possibility as $part) {
                            if (is_array($part) && $part[0] === $name) {
                                return true;
                            }
                        }
                        return false;
                    })() === false;
            }, false);
            $type = 'string';
            $parameters[$name] = [
                'name' => $name,
                'description' => '',
                'required' => !boolval($isThisParameterOptional),
                'example' => $this->generateDummyValue($type),
                'type' => $type,
            ];
        }

        return $parameters;
    }
}
