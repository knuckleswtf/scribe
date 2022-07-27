<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Response
{
    public function __construct(
        public int $status = 200,
        public ?string $content = null,
        public ?string $description = '',
    ) {
    }

    public function toArray()
    {
        return  [
            "status" => $this->status,
            "content" => $this->content,
            "description" => $this->description,
        ];
    }
}
