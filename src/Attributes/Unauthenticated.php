<?php

namespace Knuckles\Scribe\Attributes;

#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class Unauthenticated
{
    public function toArray()
    {
        return ['authenticated' => false];
    }
}
