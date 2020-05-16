<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

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

class GetFromUrlParamTag extends Strategy
{
    public $stage = 'urlParameters';

    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        foreach ($method->getParameters() as $param) {
            $paramType = $param->getType();
            if ($paramType === null) {
                continue;
            }

            $parameterClassName = $paramType->getName();

            try {
                $parameterClass = new ReflectionClass($parameterClassName);
            } catch (\ReflectionException $e) {
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

        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];

        return $this->getUrlParametersFromDocBlock($methodDocBlock->getTags());
    }

    public function getUrlParametersFromDocBlock($tags)
    {
        $parameters = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'urlParam';
            })
            ->mapWithKeys(function (Tag $tag) {
                // Format:
                // @urlParam <name> <"required" (optional)> <description>
                // Examples:
                // @urlParam id required The id of the post.
                // @urlParam user_id The ID of the user.
                preg_match('/(.+?)\s+(required\s+)?([\s\S]*)/', $tag->getContent(), $content);
                $content = preg_replace('/\s?No-example.?/', '', $content);
                if (empty($content)) {
                    // This means only name was supplied
                    [$name] = preg_split('/\s+/', $tag->getContent());
                    $required = false;
                    $description = '';
                } else {
                    [$_, $name, $required, $description] = $content;
                    $description = trim($description);
                    if ($description == 'required' && empty(trim($required))) {
                        $required = $description;
                        $description = '';
                    }
                    $required = trim($required) == 'required' ? true : false;
                }

                [$description, $value] = $this->parseExampleFromParamDescription($description, 'string');
                if (is_null($value) && ! $this->shouldExcludeExample($tag->getContent())) {
                    $value = Str::contains($description, ['number', 'count', 'page'])
                        ? $this->generateDummyValue('integer')
                        : $this->generateDummyValue('string');
                }

                return [$name => compact('description', 'required', 'value')];
            })->toArray();

        return $parameters;
    }
}
