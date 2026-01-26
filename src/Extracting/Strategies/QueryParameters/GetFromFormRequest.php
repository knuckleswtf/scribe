<?php

namespace Knuckles\Scribe\Extracting\Strategies\QueryParameters;

use Knuckles\Scribe\Extracting\Strategies\GetFromFormRequestBase;

class GetFromFormRequest extends GetFromFormRequestBase
{
    protected string $customParameterDataMethodName = 'queryParameters';

    protected function isFormRequestMeantForThisStrategy(\ReflectionClass $formRequestReflectionClass): bool
    {
        // Only use this FormRequest for query params if there's "Query parameters" in the docblock
        // Or there's a queryParameters() method
        $formRequestDocBlock = $formRequestReflectionClass->getDocComment();
        if (false !== strpos(strtolower($formRequestDocBlock), 'query parameters')) {
            return true;
        }

        return parent::isFormRequestMeantForThisStrategy($formRequestReflectionClass);
    }
}
