<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Header
{
    public function __construct(
        public string $name,
        public mixed $example = null
    ) {
    }

    public function toArray()
    {
        return [
            "name" => $this->name,
            "example" => $this->example,
        ];
    }
}
