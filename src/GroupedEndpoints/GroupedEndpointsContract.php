<?php

namespace Knuckles\Scribe\GroupedEndpoints;

interface GroupedEndpointsContract
{
    public function get(): array;

    public function hasEncounteredErrors(): bool;
}
