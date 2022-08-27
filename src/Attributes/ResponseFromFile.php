<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;
use Knuckles\Scribe\Extracting\Shared\ResponseFileTools;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseFromFile
{
    public function __construct(
        public string $file,
        public int $status = 200,
        public array|string $merge = [],
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
