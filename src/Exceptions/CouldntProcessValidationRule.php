<?php

namespace Knuckles\Scribe\Exceptions;

use Throwable;

class CouldntProcessValidationRule extends \RuntimeException implements ScribeException
{
    public static function forParam(string $paramName, $rule,  Throwable $innerException): CouldntProcessValidationRule
    {
        return new self(
            "Couldn't process this validation rule for the param `$paramName`: ".var_export($rule, true),
            0, $innerException
        );
    }
}
