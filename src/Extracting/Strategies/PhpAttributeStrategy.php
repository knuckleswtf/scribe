<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\ParamHelpers;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * @template T of \ReflectionAttribute
 */
abstract class PhpAttributeStrategy extends Strategy
{
    use ParamHelpers;

    /**
     * @var class-string<T>
     */
    protected string $attributeName;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): array
    {
        [$attributesOnMethod, $attributesOnController] =
            $this->getAttributes($endpointData->method, $endpointData->controller);

        return $this->extractFromAttributes($attributesOnMethod, $attributesOnController);
    }

    /**
     * @param \ReflectionFunctionAbstract $method
     * @param \ReflectionClass|null $class
     *
     * @return array{array<T>, array<T>}
     */
    protected function getAttributes(ReflectionFunctionAbstract $method, ?ReflectionClass $class = null): array
    {
        $attributesOnMethod = array_map(
            fn(ReflectionAttribute $a) => $a->newInstance(), $method->getAttributes($this->attributeName)
        );

        if ($class) {
            $attributesOnController = array_map(
                fn(ReflectionAttribute $a) => $a->newInstance(), $class->getAttributes($this->attributeName)
            );
        }

        return [$attributesOnMethod, $attributesOnController ?? []];
    }

    /**
     * @param array<T> $attributesOnMethod
     * @param array<T> $attributesOnController
     */
    abstract protected function extractFromAttributes(array $attributesOnMethod, array $attributesOnController): ?array;
}
