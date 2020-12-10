<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionUnionType;

class GetFromBodyParamTag extends Strategy
{
    public $stage = 'bodyParameters';

    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        return $this->getBodyParametersFromDocBlockInFormRequestOrMethod($route, $method);
    }

    public function getBodyParametersFromDocBlockInFormRequestOrMethod(Route $route, ReflectionFunctionAbstract $method): array
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
            } catch (ReflectionException $e) {
                continue;
            }

            // If there's a FormRequest, we check there for @bodyParam tags.
            if (class_exists(LaravelFormRequest::class) && $parameterClass->isSubclassOf(LaravelFormRequest::class)
                || class_exists(DingoFormRequest::class) && $parameterClass->isSubclassOf(DingoFormRequest::class)) {
                $formRequestDocBlock = new DocBlock($parameterClass->getDocComment());
                $bodyParametersFromDocBlock = $this->getBodyParametersFromDocBlock($formRequestDocBlock->getTags());

                if (count($bodyParametersFromDocBlock)) {
                    return $bodyParametersFromDocBlock;
                }
            }
        }

        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];

        return $this->getBodyParametersFromDocBlock($methodDocBlock->getTags());
    }

    public function getBodyParametersFromDocBlock($tags)
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
            preg_match('/(.+?)\s+(.+?)\s+(required\s+)?([\s\S]*)/', $tagContent, $content);
            if (empty($content)) {
                // this means only name and type were supplied
                [$name, $type] = preg_split('/\s+/', $tagContent);
                $required = false;
                $description = '';
            } else {
                [$_, $name, $type, $required, $description] = $content;
                $description = trim(str_replace(['No-example.', 'No-example'], '', $description));
                if ($description == 'required') {
                    $required = $description;
                    $description = '';
                }
                $required = trim($required) === 'required';
            }

            $type = $this->normalizeTypeName($type);
            [$description, $example] = $this->parseExampleFromParamDescription($description, $type);
            $value = is_null($example) && !$this->shouldExcludeExample($tagContent)
                ? $this->generateDummyValue($type)
                : $example;

            $parameters[$name] = compact('name', 'type', 'description', 'required', 'value');
        }

        return $parameters;
    }
}
