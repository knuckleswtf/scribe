<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Camel\Camel;
use Knuckles\Scribe\Commands\GenerateDocumentation;
use Knuckles\Scribe\Matching\RouteMatcherInterface;

class GroupedEndpointsFactory
{
    public function make(GenerateDocumentation $command, RouteMatcherInterface $routeMatcher, string $docsName = 'scribe'): GroupedEndpointsContract
    {
        if ($command->isForcing()) {
            return $this->makeGroupedEndpointsFromApp($command, $routeMatcher, false, $docsName);
        }

        if ($command->shouldExtract()) {
            return $this->makeGroupedEndpointsFromApp($command, $routeMatcher, true, $docsName);
        }

        return $this->makeGroupedEndpointsFromCamelDir($docsName);
    }

    protected function makeGroupedEndpointsFromApp(
        GenerateDocumentation $command,
        RouteMatcherInterface $routeMatcher,
        bool $preserveUserChanges,
        string $docsName = 'scribe'
    ): GroupedEndpointsFromApp {
        return new GroupedEndpointsFromApp($command, $routeMatcher, $preserveUserChanges, $docsName);
    }

    protected function makeGroupedEndpointsFromCamelDir(string $docsName = 'scribe'): GroupedEndpointsFromCamelDir
    {
        return new GroupedEndpointsFromCamelDir($docsName);
    }
}
