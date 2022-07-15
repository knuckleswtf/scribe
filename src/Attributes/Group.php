<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute]
class Group
{
    public function __construct(
        public string $name,
        public ?string $description,
    ){
    }
}
