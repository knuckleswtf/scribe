<?php

namespace Knuckles\Scribe\Extracting\Strategies\Headers;

use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\TagStrategyWithFormRequestFallback;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;

class GetFromHeaderTag extends TagStrategyWithFormRequestFallback
{
    use ParamHelpers;

    /**
     * @param Tag[] $tagsOnMethod
     * @param Tag[] $tagsOnClass
     *
     * @return array
     */
    public function getFromTags(array $tagsOnMethod, array $tagsOnClass = []): array
    {
        $headerTags = Utils::filterDocBlockTags([...$tagsOnClass, ...$tagsOnMethod], 'header');
        $headers = collect($headerTags)->mapWithKeys(function (Tag $tag) {
            // Format:
            // @header <name> <example>
            // Examples:
            // @header X-Custom An API header
            preg_match('/([\S]+)(.*)?/', $tag->getContent(), $content);

            [$_, $name, $example] = $content;
            $example = trim($example);
            if (empty($example)) {
                $example = $this->generateDummyValue('string');
            }

            return [$name => $example];
        })->toArray();

        return $headers;
    }
}
