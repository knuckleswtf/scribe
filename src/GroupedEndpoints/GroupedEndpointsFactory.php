<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Scribe\Commands\GenerateDocumentation;
use Knuckles\Scribe\Configuration\CacheConfiguration;
use Knuckles\Scribe\Matching\RouteMatcherInterface;

class GroupedEndpointsFactory
{
    public function make(
        GenerateDocumentation $command,
        RouteMatcherInterface $routeMatcher,
        CacheConfiguration $docsName
    ): GroupedEndpointsContract {
        if ($command->isForcing()) {
            return static::fromApp($command, $routeMatcher, false, $docsName);
        }

        if ($command->shouldExtract()) {
            return static::fromApp($command, $routeMatcher, true, $docsName);
        }

        return static::fromCamelDir($docsName);
    }

    public static function fromApp(
        GenerateDocumentation $command,
        RouteMatcherInterface $routeMatcher,
        bool $preserveUserChanges,
        CacheConfiguration $docsName
    ): GroupedEndpointsFromApp {
        return new GroupedEndpointsFromApp($command, $routeMatcher, $preserveUserChanges, $docsName);
    }

    public static function fromCamelDir(CacheConfiguration $docsName): GroupedEndpointsFromCamelDir
    {
        return new GroupedEndpointsFromCamelDir($docsName);
    }
}
