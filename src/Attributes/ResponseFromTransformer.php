<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseFromTransformer
{
    public function __construct(
        public string $name,
        public string $model,
        public int $status = 200,
        public bool $collection = false,
        public ?string $description = '',
        public array $states = [],
        public array $with = [],
        public ?string $resourceKey = null,
        /* Format: [numberPerPage, adapter]. Example: [10, SomePaginator::class] */
        public array $paginate = [],
    ) {
    }

    public function toArray()
    {
        return  [
            "status" => $this->status,
            "description" => $this->description,
            "content" => $this->getTransformerResponse(),
        ];
    }
}
