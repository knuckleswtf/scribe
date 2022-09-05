<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\ParamHelpers;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * @template T
 */
abstract class PhpAttributeStrategy extends Strategy
{
    use ParamHelpers;

    /**
     * @var string[]
     */
    protected array $attributeNames;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): array
    {
        $this->endpointData = $endpointData;
        [$attributesOnMethod, $attributesOnController] =
            $this->getAttributes($endpointData->method, $endpointData->controller);

        return $this->extractFromAttributes($attributesOnMethod, $attributesOnController, $endpointData);
    }

    /**
     * @param \ReflectionFunctionAbstract $method
     * @param \ReflectionClass|null $class
     *
     * @return array{array<T>, array<T>}
     */
    protected function getAttributes(ReflectionFunctionAbstract $method, ?ReflectionClass $class = null): array
    {
        $attributesOnMethod = collect($this->attributeNames)
            ->flatMap(fn(string $name) => $method->getAttributes($name))
            ->map(fn(ReflectionAttribute $a) => $a->newInstance())->all();

        if ($class) {
            $attributesOnController = collect($this->attributeNames)
                ->flatMap(fn(string $name) => $class->getAttributes($name))
                ->map(fn(ReflectionAttribute $a) => $a->newInstance())->all();
        }

        return [$attributesOnMethod, $attributesOnController ?? []];
    }

    /**
     * @param array<T> $attributesOnMethod
     * @param array<T> $attributesOnController
     * @param \Knuckles\Camel\Extraction\ExtractedEndpointData $endpointData
     *
     * @return array|null
     */
    abstract protected function extractFromAttributes(
        array $attributesOnMethod, array $attributesOnController,
        ExtractedEndpointData $endpointData
    ): ?array;
}
