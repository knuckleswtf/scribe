<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Response
{
    public function __construct(
        public string|array|null $content = null,
        public int               $status = 200,
        public ?string           $description = '',
    ) {
    }

    public function toArray()
    {
        return  [
            "status" => $this->status,
            "content" => match (true) {
                is_null($this->content) => null,
                is_string($this->content) => $this->content,
                default => json_encode($this->content, JSON_THROW_ON_ERROR),
            },
            "description" => $this->description,
        ];
    }
}
