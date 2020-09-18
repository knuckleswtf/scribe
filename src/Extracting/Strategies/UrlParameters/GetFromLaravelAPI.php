<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\Utils;
use ReflectionClass;
use ReflectionFunctionAbstract;

class GetFromLaravelAPI extends Strategy
{
    public $stage = 'urlParameters';

    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        if (Utils::isLumen()) {
            return null;
        }

        $parameters = [];

        $path = $alreadyExtractedData['uri'];
        preg_match_all('/\{(.*?)\}/', $path, $matches);

        foreach ($matches[1] as $match) {
            $optional = Str::endsWith($match, '?');
            $name = rtrim($match, '?');
            $type = 'string';
            $parameters[$name] = [
                'name' => $name,
                'description' => '',
                'required' => !$optional,
                'value' => $this->generateDummyValue($type),
                'type' => $type,
            ];
        }

        return $parameters;
    }
}
