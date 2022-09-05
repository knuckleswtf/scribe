<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\FindsFormRequestForMethod;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Mpociot\Reflection\DocBlock;
use ReflectionFunctionAbstract;

abstract class TagStrategyWithFormRequestFallback extends Strategy
{
    use FindsFormRequestForMethod;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $this->endpointData = $endpointData;
        return $this->getParametersFromDocBlockInFormRequestOrMethod($endpointData->route, $endpointData->method);
    }

    public function getParametersFromDocBlockInFormRequestOrMethod(Route $route, ReflectionFunctionAbstract $method): array
    {
        $classTags = RouteDocBlocker::getDocBlocksFromRoute($route)['class']?->getTags() ?: [];
        // If there's a FormRequest, w.e check there for tags.
        if ($formRequestClass = $this->getFormRequestReflectionClass($method)) {
            $formRequestDocBlock = new DocBlock($formRequestClass->getDocComment());
            $parametersFromFormRequest = $this->getFromTags($formRequestDocBlock->getTags(), $classTags);

            if (count($parametersFromFormRequest)) {
                return $parametersFromFormRequest;
            }
        }

        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];
        return $this->getFromTags($methodDocBlock->getTags(), $classTags);
    }

    /**
     * @param \Mpociot\Reflection\DocBlock\Tag[] $tagsOnMethod
     * @param \Mpociot\Reflection\DocBlock\Tag[] $tagsOnClass
     *
     * @return array
     */
    abstract public function getFromTags(array $tagsOnMethod, array $tagsOnClass = []): array;
}
