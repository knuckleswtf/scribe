<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\FindsFormRequestForMethod;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionFunctionAbstract;

class GetFromBodyParamTag extends Strategy
{
    use ParamHelpers, FindsFormRequestForMethod;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        return $this->getBodyParametersFromDocBlockInFormRequestOrMethod($endpointData->route, $endpointData->method);
    }

    public function getBodyParametersFromDocBlockInFormRequestOrMethod(Route $route, ReflectionFunctionAbstract $method): array
    {
        // Todo v4 change to overwrite FormRequest strategy individually
        // If there's a FormRequest, we check there for @queryParam tags.
        if ($formRequestClass = $this->getFormRequestReflectionClass($method)) {
            $formRequestDocBlock = new DocBlock($formRequestClass->getDocComment());
            $bodyParametersFromDocBlock = $this->getBodyParametersFromDocBlock($formRequestDocBlock->getTags());

            if (count($bodyParametersFromDocBlock)) {
                return $bodyParametersFromDocBlock;
            }
        }

        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];
        return $this->getBodyParametersFromDocBlock($methodDocBlock->getTags());
    }

    /**
     * @param Tag[] $tags
     *
     * @return array
     */
    public function getBodyParametersFromDocBlock(array $tags): array
    {
        $parameters = [];

        foreach ($tags as $tag) {
            if ($tag->getName() !== 'bodyParam') continue;

            $tagContent = trim($tag->getContent());
            // Format:
            // @bodyParam <name> <type> <"required" (optional)> <description>
            // Examples:
            // @bodyParam text string required The text.
            // @bodyParam user_id integer The ID of the user.
            preg_match('/(.+?)\s+(.+?)\s+(required\s+)?([\s\S]*)/', $tagContent, $parsedContent);

            if (empty($parsedContent)) {
                // This means only name and type were supplied
                [$name, $type] = preg_split('/\s+/', $tagContent);
                $required = false;
                $description = '';
            } else {
                [$_, $name, $type, $required, $description] = $parsedContent;
                $description = trim(str_replace(['No-example.', 'No-example'], '', $description));
                if ($description == 'required') {
                    $required = $description;
                    $description = '';
                }
                $required = trim($required) === 'required';
            }

            $type = $this->normalizeTypeName($type);
            [$description, $example] = $this->parseExampleFromParamDescription($description, $type);

            $example = is_null($example) && !$this->shouldExcludeExample($tagContent)
                ? $this->generateDummyValue($type)
                : $example;

            $parameters[$name] = compact('name', 'type', 'description', 'required', 'example');
        }

        return $parameters;
    }
}
