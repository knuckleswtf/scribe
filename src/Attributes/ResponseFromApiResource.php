<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Knuckles\Scribe\Extracting\Shared\ApiResourceResponseTools;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseFromApiResource
{
    public function __construct(
        public string $name,
        public ?string $model = null,
        public int $status = 200,
        public ?string $description = '',

        /* Mark if this should be used as a collection. Only needed if not using a ResourceCollection. */
        public ?bool $collection = null,
        public array $factoryStates = [],
        public array $with = [],

        public ?int $paginate = null,
        public ?int $simplePaginate = null,
        public array $additional = [],
    )
    {
    }

    public function modelToBeTransformed(): ?string
    {
        if (!empty($this->model)) {
            return $this->model;
        }

        return ApiResourceResponseTools::tryToInferApiResourceModel($this->name);
    }

    public function isCollection(): bool
    {
        if (!is_null($this->collection)) {
            return $this->collection;
        }

        $className = $this->name;
        return (new $className(new \Illuminate\Http\Resources\MissingValue)) instanceof ResourceCollection;
    }
}
