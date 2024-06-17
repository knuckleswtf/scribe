<?php

namespace Knuckles\Scribe\Exceptions;

class ExampleTypeNotFound extends \RuntimeException implements ScribeException
{
    public static function forTag(string $tag)
    {
        return new self(
            <<<MESSAGE
You specified the example "$tag" field in one of your custom endpoints, but we couldn't find the required type.
Did you forgot to define the response type?
MESSAGE

        );
    }
}
