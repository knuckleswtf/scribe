<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\FindsFormRequestForMethod;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Mpociot\Reflection\DocBlock;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * @template T
 */
abstract class PhpAttributeStrategy extends Strategy
{
    use ParamHelpers;
    use FindsFormRequestForMethod;

    /**
     * @var string[]
     */
    protected static array $attributeNames;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): array
    {
        $this->endpointData = $endpointData;
        [$attributesOnMethod, $attributesOnFormRequest, $attributesOnController] =
            $this->getAttributes($endpointData->method, $endpointData->controller);

        return $this->extractFromAttributes($endpointData, $attributesOnMethod, $attributesOnFormRequest, $attributesOnController);
    }

    /**
     * @param \ReflectionFunctionAbstract $method
     * @param \ReflectionClass|null $class
     *
     * @return array{array<T>, array<T>, array<T>}
     */
    protected function getAttributes(ReflectionFunctionAbstract $method, ?ReflectionClass $class = null): array
    {
        $attributesOnMethod = collect(static::$attributeNames)
            ->flatMap(fn(string $name) => $method->getAttributes($name, ReflectionAttribute::IS_INSTANCEOF))
            ->map(fn(ReflectionAttribute $a) => $a->newInstance())->all();

        // If there's a FormRequest, we check there.
        if ($formRequestClass = $this->getFormRequestReflectionClass($method)) {
            $attributesOnFormRequest = collect(static::$attributeNames)
                ->flatMap(fn(string $name) => $formRequestClass->getAttributes($name, ReflectionAttribute::IS_INSTANCEOF))
                ->map(fn(ReflectionAttribute $a) => $a->newInstance())->all();
        }

        if ($class) {
            $attributesOnController = collect(static::$attributeNames)
                ->flatMap(fn(string $name) => $class->getAttributes($name, ReflectionAttribute::IS_INSTANCEOF))
                ->map(fn(ReflectionAttribute $a) => $a->newInstance())->all();
        }

        return [$attributesOnMethod, $attributesOnFormRequest ?? [], $attributesOnController ?? [], ];
    }

    /**
     * @param array<T> $attributesOnMethod
     * @param array<T> $attributesOnController
     * @param \Knuckles\Camel\Extraction\ExtractedEndpointData $endpointData
     *
     * @return array|null
     */
    abstract protected function extractFromAttributes(
        ExtractedEndpointData $endpointData,
        array $attributesOnMethod, array $attributesOnFormRequest = [], array $attributesOnController = [],
    ): ?array;
}
