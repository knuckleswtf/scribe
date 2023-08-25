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
        public mixed $enum = null, // Can pass a list of values, or a native PHP enum
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
            "enumValues" => $this->getEnumValues(),
        ];
    }

    protected function getEnumValues(): array
    {
        if (!$this->enum) {
            return [];
        }

        if (is_array($this->enum)) {
            return $this->enum;
        }

        if (function_exists('enum_exists') && enum_exists($this->enum)) {
            return array_map(
                fn ($case) => $case->value,
                $this->enum::cases()
            );
        }

        throw new \InvalidArgumentException(
            'The enum property of a parameter must be either a PHP enum or an array of values'
        );
    }
}
