<?php

namespace Knuckles\Scribe\Writing\OpenApiSpecGenerators;

use Illuminate\Support\Arr;

class OverridesGenerator extends BaseGenerator
{
    public function root(array $root, array $groupedEndpoints): array
    {
        $overrides = $this->config->get('openapi.overrides', []);
        return array_merge($root, $overrides);
    }
}
