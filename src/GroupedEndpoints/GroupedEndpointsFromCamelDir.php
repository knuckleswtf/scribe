<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Camel\Camel;
use Knuckles\Scribe\Commands\GenerateDocumentation;

class GroupedEndpointsFromCamelDir implements GroupedEndpointsContract
{
    public function get(): array
    {
        if (!is_dir(GenerateDocumentation::$camelDir)) {
            throw new \InvalidArgumentException("Can't use --no-extraction because there are no endpoints in the " .
                GenerateDocumentation::$camelDir . " directory.");
        }

        return Camel::loadEndpointsIntoGroups(GenerateDocumentation::$camelDir);
    }

    public function hasEncounteredErrors(): bool
    {
        return false;
    }
}
