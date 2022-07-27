<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseField
{
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?string $description = '',
    ) {
    }

    public function toArray()
    {
        return [
            "name" => $this->name,
            "description" => $this->description,
            "type" => $this->type,
        ];
    }
}
