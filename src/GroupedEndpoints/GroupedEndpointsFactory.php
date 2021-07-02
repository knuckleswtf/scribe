<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Camel\Camel;
use Knuckles\Scribe\Commands\GenerateDocumentation;
use Knuckles\Scribe\Matching\RouteMatcherInterface;

class GroupedEndpointsFactory
{
    public static function make(GenerateDocumentation $command, RouteMatcherInterface $routeMatcher)
    {
        if ($command->isForcing()) {
            return new GroupedEndpointsFromApp($command, $routeMatcher, false);
        }

        if ($command->shouldExtract()) {
            return new GroupedEndpointsFromApp($command, $routeMatcher, true);
        }

        return new GroupedEndpointsFromCamelDir;
    }
}
