<?php

namespace Knuckles\Scribe\Extracting\Strategies\QueryParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\FindsFormRequestForMethod;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionFunctionAbstract;

class GetFromQueryParamTag extends Strategy
{
    use ParamHelpers, FindsFormRequestForMethod;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        return $this->getQueryParametersFromFormRequestOrMethod($endpointData->route, $endpointData->method);
    }

    public function getQueryParametersFromFormRequestOrMethod(Route $route, ReflectionFunctionAbstract $method): array
    {
        // Todo v4 change to overwrite FormRequest strategy individually
        // If there's a FormRequest, we check there for @queryParam tags.
        if ($formRequestClass = $this->getFormRequestReflectionClass($method)) {
            $formRequestDocBlock = new DocBlock($formRequestClass->getDocComment());
            $queryParametersFromDocBlock = $this->getQueryParametersFromDocBlock($formRequestDocBlock->getTags());

            if (count($queryParametersFromDocBlock)) {
                return $queryParametersFromDocBlock;
            }
        }

        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];
        return $this->getQueryParametersFromDocBlock($methodDocBlock->getTags());
    }

    /**
     * @param Tag[] $tags
     *
     * @return array[]
     */
    public function getQueryParametersFromDocBlock(array $tags): array
    {
        $parameters = [];

        foreach ($tags as $tag) {
            if ($tag->getName() !== 'queryParam') continue;

            // Format:
            // @queryParam <name> <type (optional)> <"required" (optional)> <description>
            // Examples:
            // @queryParam text required The text.
            // @queryParam user_id integer The ID of the user.

            $tagContent = trim($tag->getContent());
            preg_match('/(.+?)\s+([a-zA-Z\[\]]+\s+)?(required\s+)?([\s\S]*)/', $tagContent, $content);

            if (empty($content)) {
                // This means only name was supplied
                $name = $tagContent;
                $required = false;
                $description = '';
                $type = 'string';
            } else {
                [$_, $name, $type, $required, $description] = $content;

                $description = trim(str_replace(['No-example.', 'No-example'], '', $description));
                if ($description === 'required') {
                    // No description was supplied
                    $required = true;
                    $description = '';
                } else {
                    $required = trim($required) === 'required';
                }

                $type = trim($type);
                if ($type) {
                    if ($type === 'required') {
                        // Type wasn't supplied
                        $type = 'string';
                        $required = true;
                    } else {
                        $type = $this->normalizeTypeName($type);
                        // Type in annotation is optional
                        if (!$this->isSupportedTypeInDocBlocks($type)) {
                            // Then that wasn't a type, but part of the description
                            $description = trim("$type $description");
                            $type = '';
                        }
                    }
                } else if ($this->isSupportedTypeInDocBlocks($description)) {
                    // Only type was supplied
                    $type = $description;
                    $description = '';
                }

                $type = empty($type)
                    ? (Str::contains(strtolower($description), ['number', 'count', 'page']) ? 'integer' : 'string')
                    : $this->normalizeTypeName($type);

            }

            [$description, $example] = $this->parseExampleFromParamDescription($description, $type);
            if (is_null($example) && !$this->shouldExcludeExample($tagContent)) {
                $example = $this->generateDummyValue($type);
            }

            $parameters[$name] = compact('name', 'description', 'required', 'example', 'type');
        }

        return $parameters;
    }
}
