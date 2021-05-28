<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionException;
use ReflectionUnionType;

class GetFromUrlParamTag extends Strategy
{
    use ParamHelpers;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        foreach ($endpointData->method->getParameters() as $param) {
            $paramType = $param->getType();
            if ($paramType === null) {
                continue;
            }

            if (class_exists(ReflectionUnionType::class)
                && $paramType instanceof ReflectionUnionType) {
                continue;
            }

            $parameterClassName = $paramType->getName();

            try {
                $parameterClass = new ReflectionClass($parameterClassName);
            } catch (ReflectionException $e) {
                continue;
            }

            // If there's a FormRequest, we check there for @urlParam tags.
            if (class_exists(LaravelFormRequest::class) && $parameterClass->isSubclassOf(LaravelFormRequest::class)
                || class_exists(DingoFormRequest::class) && $parameterClass->isSubclassOf(DingoFormRequest::class)) {
                $formRequestDocBlock = new DocBlock($parameterClass->getDocComment());
                $urlParametersFromDocBlock = $this->getUrlParametersFromDocBlock($formRequestDocBlock->getTags());

                if (count($urlParametersFromDocBlock)) {
                    return $urlParametersFromDocBlock;
                }
            }
        }

        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route)['method'];

        return $this->getUrlParametersFromDocBlock($methodDocBlock->getTags());
    }

    /**
     * @param Tag[] $tags
     *
     * @return array[]
     */
    public function getUrlParametersFromDocBlock(array $tags): array
    {
        $parameters = [];

        foreach ($tags as $tag) {
            if ($tag->getName() !== 'urlParam') continue;

            $tagContent = trim($tag->getContent());
            // Format:
            // @urlParam <name> <type (optional)> <"required" (optional)> <description>
            // Examples:
            // @urlParam id string required The id of the post.
            // @urlParam user_id The ID of the user.

            // We match on all the possible types for URL parameters. It's a limited range, so no biggie.
            preg_match('/(\w+?)\s+((int|integer|string|float|double|number)\s+)?(required\s+)?([\s\S]*)/', $tagContent, $content);
            if (empty($content)) {
                // This means only name was supplied
                $name = trim($tagContent);
                $required = false;
                $description = '';
                $type = 'string';
            } else {
                [$_, $name, $__, $type, $required, $description] = $content;
                $description = trim(str_replace(['No-example.', 'No-example'], '', $description));
                if ($description === 'required') {
                    $required = true;
                    $description = '';
                } else {
                    $required = trim($required) === 'required';
                }

                if (empty($type) && $this->isSupportedTypeInDocBlocks($description)) {
                    // Only type was supplied
                    $type = $description;
                    $description = '';
                }

                $type = empty($type)
                    ? (Str::contains($description, ['number', 'count', 'page']) ? 'integer' : 'string')
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
