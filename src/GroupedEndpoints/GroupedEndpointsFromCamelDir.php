<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Camel\Camel;
use Knuckles\Scribe\Configuration\PathConfig;

class GroupedEndpointsFromCamelDir implements GroupedEndpointsContract
{
    protected PathConfig $pathConfig;

    public function __construct(PathConfig $pathConfig)
    {
        $this->pathConfig = $pathConfig;
    }

    public function get(): array
    {
        if (!is_dir(Camel::camelDir($this->pathConfig))) {
            throw new \InvalidArgumentException(
                "Can't use --no-extraction because there are no endpoints in the " . Camel::camelDir($this->pathConfig) . " directory."
            );
        }

        return Camel::loadEndpointsIntoGroups(Camel::camelDir($this->pathConfig));
    }

    public function hasEncounteredErrors(): bool
    {
        return false;
    }
}
