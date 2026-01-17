<?php

namespace Knuckles\Scribe\Exceptions;

class ProblemParsingValidationRules extends \RuntimeException implements ScribeException
{
    public static function forParam(string $paramName, \Throwable $innerException): self
    {
        return new self(
            "Problem processing validation rules for the param `{$paramName}`: {$innerException->getMessage()}",
            0,
            $innerException
        );
    }
}
