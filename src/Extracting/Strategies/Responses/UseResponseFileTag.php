<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Get a response from from a file in the docblock ( @responseFile ).
 */
class UseResponseFileTag extends Strategy
{
    /**
     * @param Route $route
     * @param \ReflectionClass $controller
     * @param \ReflectionFunctionAbstract $method
     * @param array $routeRules
     * @param array $alreadyExtractedData
     *
     * @return array|null
     *
     */
    public function __invoke(Route $route, \ReflectionClass $controller, \ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        return $this->getFileResponses($methodDocBlock->getTags());
    }

    /**
     * Get the response from the file if available.
     *
     * @param array $tags
     *
     * @return array|null
     */
    public function getFileResponses(array $tags)
    {
        // Avoid "holes" in the keys of the filtered array, by using array_values on the filtered array
        $responseFileTags = array_values(
            array_filter($tags, function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'responsefile';
            })
        );

        if (empty($responseFileTags)) {
            return null;
        }

        $responses = array_map(function (Tag $responseFileTag) {
            preg_match('/^(\d{3})?\s*(.*?)({.*})?$/', $responseFileTag->getContent(), $result);
            [$_, $status, $mainContent] = $result;
            $json = $result[3] ?? null;

            ['attributes' => $attributes, 'content' => $filePath] = a::parseIntoContentAndAttributes($mainContent, ['status', 'scenario']);

            $status = $attributes['status'] ?: ($status ?: 200);
            $description = $attributes['scenario'] ? "$status, {$attributes['scenario']}" : "$status";

            if (!file_exists($filePath)) {
                // Try Laravel storage folder
                if (!file_exists(storage_path($filePath))) {
                    c::warn("@responseFile {$filePath} does not exist");

                    return [
                        'content' => null,
                        'status' => (int)$status,
                        'description' => $description,
                    ];
                }

                $filePath = storage_path($filePath);
            }
            $content = file_get_contents($filePath, true);
            if ($json) {
                $json = str_replace("'", '"', $json);
                $content = json_encode(array_merge(json_decode($content, true), json_decode($json, true)));
            }


            return [
                'content' => $content,
                'status' => (int)$status,
                'description' => $description,
            ];
        }, $responseFileTags);

        return $responses;
    }
}
