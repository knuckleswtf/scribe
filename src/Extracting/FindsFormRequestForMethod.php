<?php

namespace Knuckles\Scribe\Extracting;

use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Dingo\Api\Http\FormRequest as DingoFormRequest;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionUnionType;

trait FindsFormRequestForMethod
{
    protected function getFormRequestReflectionClass(ReflectionFunctionAbstract $method): ?ReflectionClass
    {
        foreach ($method->getParameters() as $argument) {
            $argType = $argument->getType();
            if ($argType === null || $argType instanceof ReflectionUnionType) continue;

            $argumentClassName = $argType->getName();

            if (!class_exists($argumentClassName)) continue;

            try {
                $argumentClass = new ReflectionClass($argumentClassName);
            } catch (ReflectionException $e) {
                continue;
            }

            if (
                (class_exists(LaravelFormRequest::class) && $argumentClass->isSubclassOf(LaravelFormRequest::class))
                || (class_exists(DingoFormRequest::class) && $argumentClass->isSubclassOf(DingoFormRequest::class))) {
                return $argumentClass;
            }
        }

        return null;
    }
}
