<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Authenticated
{
    public function __construct(
        public ?bool $authenticated = true,
    )
    {
    }

    public function toArray()
    {
        return ["authenticated" => $this->authenticated];
    }
}
