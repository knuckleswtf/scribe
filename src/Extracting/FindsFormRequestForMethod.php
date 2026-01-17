<?php

namespace Knuckles\Scribe\Extracting;

use Illuminate\Foundation\Http\FormRequest;

trait FindsFormRequestForMethod
{
    protected function getFormRequestReflectionClass(\ReflectionFunctionAbstract $method): ?\ReflectionClass
    {
        foreach ($method->getParameters() as $argument) {
            $argType = $argument->getType();
            if ($argType === null || $argType instanceof \ReflectionUnionType) {
                continue;
            }

            $argumentClassName = $argType->getName();

            if (! class_exists($argumentClassName)) {
                continue;
            }

            try {
                $argumentClass = new \ReflectionClass($argumentClassName);
            } catch (\ReflectionException $e) {
                continue;
            }

            if ($argumentClass->isSubclassOf(FormRequest::class)) {
                return $argumentClass;
            }
        }

        return null;
    }
}
