<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Get a response from the docblock ( @response ).
 */
class UseResponseTag extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route);
        return $this->getDocBlockResponses($docBlocks['method']->getTags());
    }

    /**
     * @param Tag[] $tags
     */
    public function getDocBlockResponses(array $tags): ?array
    {
        $responseTags = Utils::filterDocBlockTags($tags, 'response');

        if (empty($responseTags)) return null;

        $responses = array_map(function (Tag $responseTag) {
            // Status code (optional) followed by response
            preg_match('/^(\d{3})?\s?([\s\S]*)$/', $responseTag->getContent(), $result);

            $status = $result[1] ?: 200;
            $content = $result[2] ?: '{}';

            ['fields' => $fields, 'content' => $content] = a::parseIntoContentAndFields($content, ['status', 'scenario']);

            $status = $fields['status'] ?: $status;
            $description = $fields['scenario'] ?: "";

            return ['content' => $content, 'status' => (int) $status, 'description' => $description];
        }, $responseTags);

        return $responses;
    }
}
