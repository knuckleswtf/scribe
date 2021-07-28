<?php

namespace Knuckles\Scribe\Exceptions;

class CouldntFindFactory extends \RuntimeException implements ScribeException
{
    public static function forModel(string $modelName): CouldntFindFactory
    {
        return new self("Couldn't find the Eloquent model factory. Did you add the HasFactory trait to your $modelName model?");
    }
}
