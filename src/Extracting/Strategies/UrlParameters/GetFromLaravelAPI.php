<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Knuckles\Camel\Endpoint\EndpointData;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\Utils;

class GetFromLaravelAPI extends Strategy
{
    use ParamHelpers;

    public string $stage = 'urlParameters';

    public function __invoke(EndpointData $endpointData, array $routeRules)
    {
        if (Utils::isLumen()) {
            return null;
        }

        $parameters = [];

        $path = $endpointData->uri;
        preg_match_all('/\{(.*?)\}/', $path, $matches);

        foreach ($matches[1] as $match) {
            $optional = Str::endsWith($match, '?');
            $name = rtrim($match, '?');
            $type = 'string';
            $parameters[$name] = [
                'name' => $name,
                'description' => '',
                'required' => !$optional,
                'example' => $this->generateDummyValue($type),
                'type' => $type,
            ];
        }

        return $parameters;
    }
}
