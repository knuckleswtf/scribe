<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use FastRoute\RouteParser\Std;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\Utils;
use ReflectionClass;
use ReflectionFunctionAbstract;

class GetFromLumenAPI extends Strategy
{
    public $stage = 'urlParameters';

    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        if (!Utils::isLumen()) {
            return null;
        }

        $path = $alreadyExtractedData['uri'];

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
                'value' => $this->generateDummyValue($type),
                'type' => $type,
            ];
        }

        return $parameters;
    }
}
