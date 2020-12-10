<?php

namespace Knuckles\Scribe\Extracting\Strategies\QueryParameters;

use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionUnionType;

class GetFromQueryParamTag extends Strategy
{
    public $stage = 'queryParameters';

    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        return $this->getQueryParametersFromFormRequestOrMethod($route, $method);
    }

    public function getQueryParametersFromFormRequestOrMethod(Route $route, ReflectionFunctionAbstract $method): array
    {
        foreach ($method->getParameters() as $param) {
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
            } catch (\ReflectionException $e) {
                continue;
            }

            // If there's a FormRequest, we check there for @queryParam tags.
            if (class_exists(LaravelFormRequest::class) && $parameterClass->isSubclassOf(LaravelFormRequest::class)
                || class_exists(DingoFormRequest::class) && $parameterClass->isSubclassOf(DingoFormRequest::class)) {
                $formRequestDocBlock = new DocBlock($parameterClass->getDocComment());
                $queryParametersFromDocBlock = $this->getQueryParametersFromDocBlock($formRequestDocBlock->getTags());

                if (count($queryParametersFromDocBlock)) {
                    return $queryParametersFromDocBlock;
                }
            }
        }

        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];

        return $this->getQueryParametersFromDocBlock($methodDocBlock->getTags());
    }

    /**
     * @param Tag[] $tags
     *
     * @return array[]
     */
    public function getQueryParametersFromDocBlock(array $tags)
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

            $type = '';
            if (empty($content)) {
                // this means only name was supplied
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

            [$description, $value] = $this->parseExampleFromParamDescription($description, $type);
            if (is_null($value) && !$this->shouldExcludeExample($tagContent)) {
                $value = $this->generateDummyValue($type);
            }

            $parameters[$name] = compact('name', 'description', 'required', 'value', 'type');
        }

        return $parameters;
    }
}
