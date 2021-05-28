<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Get a response from the docblock ( @response ).
 */
class UseResponseTag extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route);
        $methodDocBlock = $docBlocks['method'];

        return $this->getDocBlockResponses($methodDocBlock->getTags());
    }

    /**
     * Get the response from the docblock if available.
     *
     * @param Tag[] $tags
     *
     * @return array|null
     */
    public function getDocBlockResponses(array $tags): ?array
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
            $description = $attributes['scenario'] ? "$status, {$attributes['scenario']}" : "$status";

            return ['content' => $content, 'status' => (int) $status, 'description' => $description];
        }, $responseTags);

        return $responses;
    }
}
