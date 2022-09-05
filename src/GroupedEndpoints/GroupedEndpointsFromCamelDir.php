<?php

namespace Knuckles\Scribe\GroupedEndpoints;

use Knuckles\Camel\Camel;

class GroupedEndpointsFromCamelDir implements GroupedEndpointsContract
{
    protected string $docsName;

    public function __construct(string $docsName = 'scribe')
    {
        $this->docsName = $docsName;
    }

    public function get(): array
    {
        if (!is_dir(Camel::camelDir($this->docsName))) {
            throw new \InvalidArgumentException(
                "Can't use --no-extraction because there are no endpoints in the " . Camel::camelDir($this->docsName) . " directory."
            );
        }

        return Camel::loadEndpointsIntoGroups(Camel::camelDir($this->docsName));
    }

    public function hasEncounteredErrors(): bool
    {
        return false;
    }
}
