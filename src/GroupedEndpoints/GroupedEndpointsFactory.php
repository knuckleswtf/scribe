<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Scribe\Commands\GenerateDocumentation;
use Knuckles\Scribe\Configuration\PathConfig;
use Knuckles\Scribe\Matching\RouteMatcherInterface;

class GroupedEndpointsFactory
{
    /**
     * @param GenerateDocumentation $command
     * @param RouteMatcherInterface $routeMatcher
     * @param PathConfig $pathConfig
     * @return GroupedEndpointsContract
     */
    public function make(
        GenerateDocumentation $command,
        RouteMatcherInterface $routeMatcher,
        PathConfig $pathConfig
    ): GroupedEndpointsContract {
        if ($command->isForcing()) {
            return static::fromApp($command, $routeMatcher, false, $pathConfig);
        }

        if ($command->shouldExtract()) {
            return static::fromApp($command, $routeMatcher, true, $pathConfig);
        }

        return static::fromCamelDir($pathConfig);
    }

    /**
     * @param GenerateDocumentation $command
     * @param RouteMatcherInterface $routeMatcher
     * @param bool $preserveUserChanges
     * @param PathConfig $pathConfig
     * @return GroupedEndpointsFromApp
     */
    public static function fromApp(
        GenerateDocumentation $command,
        RouteMatcherInterface $routeMatcher,
        bool $preserveUserChanges,
        PathConfig $pathConfig
    ): GroupedEndpointsFromApp {
        return new GroupedEndpointsFromApp($command, $routeMatcher, $pathConfig, $preserveUserChanges);
    }

    /**
     * @param PathConfig $pathConfig
     * @return GroupedEndpointsFromCamelDir
     */
    public static function fromCamelDir(PathConfig $pathConfig): GroupedEndpointsFromCamelDir
    {
        return new GroupedEndpointsFromCamelDir($pathConfig);
    }
}
