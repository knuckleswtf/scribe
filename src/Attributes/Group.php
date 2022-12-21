<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Group
{
    public function __construct(
        public string $name,
        public ?string $description = '',
        /** You can use the separate #[Authenticated] attribute, or pass authenticated: false to this. */
        public ?bool   $authenticated = null,
    ) {
    }

    public function toArray()
    {
        $data = [
            "groupName" => $this->name,
            "groupDescription" => $this->description,
        ];

        if (!is_null($this->authenticated)) {
            $data["authenticated"] = $this->authenticated;
        }

        return $data;
    }
}
