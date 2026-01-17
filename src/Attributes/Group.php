<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Group
{
    public function __construct(
        public mixed $name,
        public ?string $description = '',
        /** You can use the separate #[Authenticated] attribute, or pass authenticated: false to this. */
        public ?bool $authenticated = null,
    ) {}

    public function toArray()
    {
        $data = [
            'groupName' => $this->getName(),
            'groupDescription' => $this->description,
        ];

        if (! is_null($this->authenticated)) {
            $data['authenticated'] = $this->authenticated;
        }

        return $data;
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
            'The name property of a group must be either a PHP Backed Enum or a string'
        );
    }
}
