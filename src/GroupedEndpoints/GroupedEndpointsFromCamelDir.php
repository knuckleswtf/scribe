<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Camel\Camel;
use Knuckles\Scribe\Tools\PathConfig;

class GroupedEndpointsFromCamelDir implements GroupedEndpointsContract
{

    public function __construct(protected PathConfig $paths)
    {
    }

    public function get(): array
    {
        if (!is_dir(Camel::camelDir($this->paths))) {
            throw new \InvalidArgumentException(
                "Can't use --no-extraction because there are no endpoints in the " . Camel::camelDir($this->paths) . " directory."
            );
        }

        return Camel::loadEndpointsIntoGroups(Camel::camelDir($this->paths));
    }

    public function hasEncounteredErrors(): bool
    {
        return false;
    }
}
