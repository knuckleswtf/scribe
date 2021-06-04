<?php

namespace Knuckles\Scribe\Extracting\Strategies\QueryParameters;

use Knuckles\Scribe\Extracting\Strategies\GetFromFormRequestBase;
use ReflectionClass;

class GetFromFormRequest extends GetFromFormRequestBase
{
    protected string $customParameterDataMethodName = 'queryParameters';

    protected function isFormRequestMeantForThisStrategy(ReflectionClass $formRequestReflectionClass): bool
    {
        // Only use this FormRequest for query params if there's "Query parameters" in the docblock
        // Or there's a queryParameters() method
        $formRequestDocBlock = $formRequestReflectionClass->getDocComment();
        if (strpos(strtolower($formRequestDocBlock), "query parameters") !== false) {
            return true;
        }

        return parent::isFormRequestMeantForThisStrategy($formRequestReflectionClass);
    }
}

