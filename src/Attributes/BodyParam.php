<?php

namespace Knuckles\Scribe\Attributes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class BodyParam extends GenericParam {}
