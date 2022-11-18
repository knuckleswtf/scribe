<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Shared\ResponseFileTools;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Get a response from a file path in the docblock ( @responseFile ).
 */
class UseResponseFileTag extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route);
        return $this->getFileResponses($docBlocks['method']->getTags());
    }

    /**
     * @param Tag[] $tags
     */
    public function getFileResponses(array $tags): ?array
    {
        $responseFileTags = Utils::filterDocBlockTags($tags, 'responsefile');

        if (empty($responseFileTags)) return null;

        $responses = array_map(function (Tag $responseFileTag) {
            preg_match('/^(\d{3})?\s*(.*?)({.*})?$/', $responseFileTag->getContent(), $result);
            [$_, $status, $mainContent] = $result;
            $json = $result[3] ?? null;

            ['fields' => $fields, 'content' => $filePath] = a::parseIntoContentAndFields($mainContent, ['status', 'scenario']);

            $status = $fields['status'] ?: ($status ?: 200);
            $description = $fields['scenario'] ?: "";
            $content = ResponseFileTools::getResponseContents($filePath, $json);

            return [
                'content' => $content,
                'status' => (int)$status,
                'description' => $description,
            ];
        }, $responseFileTags);

        return $responses;
    }
}
