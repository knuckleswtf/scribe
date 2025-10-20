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
        public ?bool $nullable = false,
        public ?bool $deprecated = false,
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
            'nullable' => $this->nullable,
            'deprecated' => $this->deprecated,
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

        if (enum_exists($this->enum) && method_exists($this->enum, 'tryFrom')) {
            return array_map(
            // $case->value only exists on BackedEnums, not UnitEnums
            // method_exists($enum, 'tryFrom') implies the enum is a BackedEnum
            // @phpstan-ignore-next-line
                fn ($case) => $case->value,
                $this->enum::cases()
            );
        }

        throw new \InvalidArgumentException(
            'The enum property of a parameter must be either a PHP enum or an array of values'
        );
    }
}
