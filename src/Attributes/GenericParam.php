<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class GenericParam
{
    public function __construct(
        public string $name,
        public ?string $type = 'string',
        public ?string $description = '',
        public ?bool $required = true,
        public mixed $example = null, /* Pass 'No-example' to omit the example */
    ) {
    }

    public function toArray()
    {
        return [
            "name" => $this->name,
            "description" => $this->description,
            "type" => $this->type,
            "required" => $this->required,
            "example" => $this->example,
        ];
    }
}
