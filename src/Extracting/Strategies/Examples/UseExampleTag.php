<?php

namespace Knuckles\Scribe\Extracting\Strategies\Examples;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Exceptions\ExampleResponseStatusCodeNotFound;
use Knuckles\Scribe\Exceptions\ExampleTypeNotFound;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Shared\ResponseFileTools;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Get an example from the docblock ( @example ).
 */
class UseExampleTag extends Strategy
{
    const TYPES = [
        'request',
        'response',
    ];

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route);
        return $this->getDocBlockExamples($docBlocks['method']->getTags());
    }

    /**
     * @param Tag[] $tags
     */
    public function getDocBlockExamples(array $tags): ?array
    {
        $exampleTags = Utils::filterDocBlockTags($tags, 'example');

        if (empty($exampleTags)) return null;

        $examples = array_map(function (Tag $exampleTag) {
            $content = $exampleTag->getContent();

            ['fields' => $fields, 'content' => $content] = a::parseIntoContentAndFields($content, ['type', 'scenario', 'file']);

            $description = $fields['scenario'] ?: "";
            $type = $fields['type'] ?: "";
            $file = $fields['file'] ?: "";
            $meta = [];

            if (empty($type) || ! in_array($type, self::TYPES)) {
                // Type is required
                throw new ExampleTypeNotFound();
            }

            if ($type === 'response') {
                // Extract the status code
                preg_match('/status=(\d+)/', $content, $statusMatches);

                if (isset($statusMatches[1])) {
                    $meta['status'] = (int) $statusMatches[1];
                } else {
                    // Status code is required for type response
                    throw new ExampleResponseStatusCodeNotFound();
                }

                // Remove the status code
                $content = preg_replace('/status=(\d+)/', '', $content);
            }

            if (! empty($file)) {
                $json = json_decode($content, true) ?? null;

                $content = ResponseFileTools::getResponseContents($file, $json);
            }

            return ['type' => $type, 'meta' => $meta, 'content' => $content, 'description' => $description];
        }, $exampleTags);

        return $examples;
    }
}
