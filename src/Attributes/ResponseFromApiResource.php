<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseFromApiResource
{
    public function __construct(
        public string $name,
        public string $model,
        public int $status = 200,
        public bool $collection = false, /* Mark if this should be used as a collection */

        public ?string $description = '',
        public array $states = [],
        public array $with = [],

        public ?int $paginate = null,
        public ?int $simplePaginate = null,
    ) {
    }

    public function toArray()
    {
        return  [
            "status" => $this->status,
            "description" => $this->description,
            "content" => $this->getApiResourceResponse(),
        ];
    }
}
