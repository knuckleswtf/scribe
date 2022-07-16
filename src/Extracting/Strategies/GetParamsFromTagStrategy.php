<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\FindsFormRequestForMethod;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionFunctionAbstract;

abstract class GetParamsFromTagStrategy extends Strategy
{
    use ParamHelpers, FindsFormRequestForMethod;

    protected string $tagName = "";

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        return $this->getParametersFromDocBlockInFormRequestOrMethod($endpointData->route, $endpointData->method);
    }

    public function getParametersFromDocBlockInFormRequestOrMethod(Route $route, ReflectionFunctionAbstract $method): array
    {
        $classTags = RouteDocBlocker::getDocBlocksFromRoute($route)['class']?->getTags() ?: [];
        // If there's a FormRequest, we check there for tags.
        if ($formRequestClass = $this->getFormRequestReflectionClass($method)) {
            $formRequestDocBlock = new DocBlock($formRequestClass->getDocComment());
            $bodyParametersFromDocBlock = $this->getParametersFromTags($formRequestDocBlock->getTags(), $classTags);

            if (count($bodyParametersFromDocBlock)) {
                return $bodyParametersFromDocBlock;
            }
        }

        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];
        return $this->getParametersFromTags($methodDocBlock->getTags(), $classTags);
    }

    /**
     * @param Tag[] $tagsOnMethod
     * @param Tag[] $tagsOnClass
     *
     * @return array[]
     */
    public function getParametersFromTags(array $tagsOnMethod, array $tagsOnClass = []): array
    {
        $parameters = [];

        foreach ($tagsOnClass as $tag) {
            if (strtolower($tag->getName()) !== strtolower($this->tagName)) continue;

            $parameterData = $this->parseTag(trim($tag->getContent()));
            $parameters[$parameterData['name']] = $parameterData;
        }

        foreach ($tagsOnMethod as $tag) {
            if (strtolower($tag->getName()) !== strtolower($this->tagName)) continue;

            $parameterData = $this->parseTag(trim($tag->getContent()));
            $parameters[$parameterData['name']] = $parameterData;
        }

        return $parameters;
    }

    abstract protected function parseTag(string $tagContent): array;

    protected function getDescriptionAndExample(string $description, string $type, string $tagContent): array
    {
        [$description, $example] = $this->parseExampleFromParamDescription($description, $type);
        $example = $this->setExampleIfNeeded($example, $type, $tagContent);
        return [$description, $example];
    }

    protected function setExampleIfNeeded(mixed $currentExample, string $type, string $tagContent): mixed
    {
        return (is_null($currentExample) && !$this->shouldExcludeExample($tagContent))
            ? $this->generateDummyValue($type)
            : $currentExample;
    }
}
