<?php

namespace Knuckles\Scribe\Attributes;

#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class Subgroup
{
    public function __construct(
        public mixed $name,
        public ?string $description = '',
    ) {}

    public function toArray()
    {
        return [
            'subgroup' => $this->getName(),
            'subgroupDescription' => $this->description,
        ];
    }

    protected function getName(): string
    {
        if (is_string($this->name)) {
            return $this->name;
        }

        if (interface_exists('BackedEnum') && is_a($this->name, 'BackedEnum')) {
            return $this->name->value;
        }

        throw new \InvalidArgumentException(
            'The name property of a subgroup must be either a PHP Backed Enum or a string'
        );
    }
}
