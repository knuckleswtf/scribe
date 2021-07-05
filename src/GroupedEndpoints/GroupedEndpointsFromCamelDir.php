<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Camel\Camel;
use Knuckles\Scribe\Commands\GenerateDocumentation;

class GroupedEndpointsFromCamelDir implements GroupedEndpointsContract
{
    public function get(): array
    {
        if (!is_dir(Camel::$camelDir)) {
            throw new \InvalidArgumentException(
                "Can't use --no-extraction because there are no endpoints in the " . Camel::$camelDir . " directory."
            );
        }

        return Camel::loadEndpointsIntoGroups(Camel::$camelDir);
    }

    public function hasEncounteredErrors(): bool
    {
        return false;
    }
}
