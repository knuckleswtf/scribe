<?php

namespace Knuckles\Scribe\Extracting\Strategies\Headers;

use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionUnionType;

class GetFromHeaderTag extends Strategy
{
    public $stage = 'headers';

    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
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

            // If there's a FormRequest, we check there for @header tags.
            if (class_exists(LaravelFormRequest::class) && $parameterClass->isSubclassOf(LaravelFormRequest::class)
                || class_exists(DingoFormRequest::class) && $parameterClass->isSubclassOf(DingoFormRequest::class)) {
                $formRequestDocBlock = new DocBlock($parameterClass->getDocComment());
                $headersFromDocBlock = $this->getHeadersFromDocBlock($formRequestDocBlock->getTags());

                if (count($headersFromDocBlock)) {
                    return $headersFromDocBlock;
                }
            }
        }

        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];

        return $this->getHeadersFromDocBlock($methodDocBlock->getTags());
    }

    public function getHeadersFromDocBlock($tags)
    {
        $headers = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'header';
            })
            ->mapWithKeys(function (Tag $tag) {
                // Format:
                // @header <name> <example>
                // Examples:
                // @header X-Custom An API header
                preg_match('/([\S]+)(.*)?/', $tag->getContent(), $content);

                [$_, $name, $value] = $content;
                $value = trim($value);
                if (empty($value)) {
                    $value = $this->generateDummyValue('string');
                }

                return [$name => $value];
            })->toArray();

        return $headers;
    }
}
