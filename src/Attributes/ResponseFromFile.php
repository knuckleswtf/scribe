<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;
use Knuckles\Scribe\Extracting\Shared\ResponseFileTools;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseFromFile
{
    public function __construct(
        public int $status = 200,
        public ?string $file = null,
        public array $merge = [],
        public ?string $description = '',
    ) {
    }

    public function toArray()
    {
        return  [
            "status" => $this->status,
            "description" => $this->description,
            "content" => ResponseFileTools::getResponseContents($this->file, $this->merge)
        ];
    }
}
