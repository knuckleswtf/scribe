<?php

namespace Knuckles\Scribe\Extracting\Strategies\Headers;

use Barryvdh\Reflection\DocBlock\Tag;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\TagStrategyWithFormRequestFallback;
use Knuckles\Scribe\Tools\Utils;

class GetFromHeaderTag extends TagStrategyWithFormRequestFallback
{
    use ParamHelpers;

    /**
     * @param  Tag[]  $tagsOnMethod
     * @param  Tag[]  $tagsOnClass
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
            $example = mb_trim($example);
            if (empty($example)) {
                $example = $this->generateDummyValue('string');
            }

            return [$name => $example];
        })->toArray();

        return $headers;
    }
}
