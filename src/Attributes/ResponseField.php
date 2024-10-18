<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseField extends GenericParam
{
    // Don't default to string; type inference is currently handled by the normalizer
    // TODO change this in the future
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?string $description = '',
        public ?bool $required = true,
        public mixed $example = null, /* Pass 'No-example' to omit the example */
        public mixed $enum = null, // Can pass a list of values, or a native PHP enum,
        public ?bool $nullable = false,
    ) {
    }
}
