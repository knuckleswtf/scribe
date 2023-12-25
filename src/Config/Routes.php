<?php

namespace Knuckles\Scribe\Config;

class Routes
{
    public static function match(
        array $prefixes = ['api/*'],
        array $domains = ['*'],
        array $dingoVersions = ['v1'],
        array $alwaysInclude = [],
        array $alwaysExclude = [],
    ): static
    {
        return new static(...get_defined_vars());
    }

    public function __construct(
        public array $prefixes = [],
        public array $domains = [],
        public array $dingoVersions = [],
        public array $alwaysInclude = [],
        public array $alwaysExclude = []
    )
    {
    }
}
