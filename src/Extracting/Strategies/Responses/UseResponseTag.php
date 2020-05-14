<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Get a response from the docblock ( @response ).
 */
class UseResponseTag extends Strategy
{
    /**
     * @param Route $route
     * @param \ReflectionClass $controller
     * @param \ReflectionFunctionAbstract $method
     * @param array $routeRules
     * @param array $alreadyExtractedData
     *
     * @return array|null
     *@throws \Exception
     *
     */
    public function __invoke(Route $route, \ReflectionClass $controller, \ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        return $this->getDocBlockResponses($methodDocBlock->getTags());
    }

    /**
     * Get the response from the docblock if available.
     *
     * @param array $tags
     *
     * @return array|null
     */
    public function getDocBlockResponses(array $tags)
    {
        $responseTags = array_values(
            array_filter($tags, function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'response';
            })
        );

        if (empty($responseTags)) {
            return null;
        }

        $responses = array_map(function (Tag $responseTag) {
            // Status code (optional) followed by response
            preg_match('/^(\d{3})?\s?([\s\S]*)$/', $responseTag->getContent(), $result);

            $status = $result[1] ?: 200;
            $content = $result[2] ?: '{}';

            ['attributes' => $attributes, 'content' => $content] = a::parseIntoContentAndAttributes($content, ['status', 'scenario']);

            $status = $attributes['status'] ?: $status;
            $description = $attributes['scenario'] ? "$status, {$attributes['scenario']}" : $status;

            return ['content' => $content, 'status' => (int) $status, 'description' => $description];
        }, $responseTags);

        return $responses;
    }
}
