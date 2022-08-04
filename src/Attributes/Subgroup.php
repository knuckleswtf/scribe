<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Subgroup
{
    public function __construct(
        public string $name,
        public ?string $description = '',
    ){
    }

    public function toArray()
    {
        return [
            "subgroup" => $this->name,
            "subgroupDescription" => $this->description,
        ];
    }
}
