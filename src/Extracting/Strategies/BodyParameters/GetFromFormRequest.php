<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Scribe\Extracting\Strategies\GetFromFormRequestBase;
use ReflectionClass;

class GetFromFormRequest extends GetFromFormRequestBase
{
    protected string $customParameterDataMethodName = 'bodyParameters';

    protected function isFormRequestMeantForThisStrategy(ReflectionClass $formRequestReflectionClass): bool
    {
        // Only use this FormRequest for body params if there's no "Query parameters" in the docblock
        // Or there's a bodyParameters() method
        $formRequestDocBlock = $formRequestReflectionClass->getDocComment();
        if (strpos(strtolower($formRequestDocBlock), "query parameters") !== false
            || $formRequestReflectionClass->hasMethod('queryParameters')) {
            return false;
        }

        return true;
    }
}

