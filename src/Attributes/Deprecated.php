<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Deprecated
{
    public function __construct(
        public ?bool $deprecated = true,
    )
    {
    }

    public function toArray()
    {
        return ["deprecated" => $this->deprecated];
    }
}
