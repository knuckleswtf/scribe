<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Camel\Camel;
use Knuckles\Scribe\Commands\GenerateDocumentation;
use Knuckles\Scribe\Matching\RouteMatcherInterface;

class GroupedEndpointsFactory
{
    public function make(GenerateDocumentation $command, RouteMatcherInterface $routeMatcher): GroupedEndpointsContract
    {
        if ($command->isForcing()) {
            return $this->makeGroupedEndpointsFromApp($command, $routeMatcher, false);
        }

        if ($command->shouldExtract()) {
            return $this->makeGroupedEndpointsFromApp($command, $routeMatcher, true);
        }

        return $this->makeGroupedEndpointsFromCamelDir();
    }

    protected function makeGroupedEndpointsFromApp(
        GenerateDocumentation $command,
        RouteMatcherInterface $routeMatcher,
        bool $preserveUserChanges
    ): GroupedEndpointsFromApp {
        return new GroupedEndpointsFromApp($command, $routeMatcher, $preserveUserChanges);
    }

    protected function makeGroupedEndpointsFromCamelDir(): GroupedEndpointsFromCamelDir
    {
        return new GroupedEndpointsFromCamelDir();
    }
}
