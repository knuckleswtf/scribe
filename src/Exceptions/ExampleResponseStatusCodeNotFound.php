<?php

namespace Knuckles\Scribe\Exceptions;

class ExampleResponseStatusCodeNotFound extends \RuntimeException implements ScribeException
{
    public static function forTag(string $tag)
    {
        return new self(
            <<<MESSAGE
You specified the response example "$tag" field in one of your custom endpoints, but we couldn't find the required status code.
Did you forgot to define the response status code?
MESSAGE

        );
    }
}
