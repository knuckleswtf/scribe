<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseFromTransformer
{
    public function __construct(
        public string  $name,
        public ?string $model = null,
        public int     $status = 200,
        public ?string $description = '',

        /* Mark if this should be used as a collection. Only needed if not using a CollectionTransformer. */
        public bool    $collection = false,
        public array   $factoryStates = [],
        public array   $with = [],
        public ?string $resourceKey = null,

        /* Format: [adapter, numberPerPage]. Example: [SomePaginator::class, 10] */
        public array $paginate = [],
    ) {
    }
}
