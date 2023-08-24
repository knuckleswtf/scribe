<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Scribe\Extracting\ParamHelpers;

abstract class GetFieldsFromTagStrategy extends TagStrategyWithFormRequestFallback
{
    use ParamHelpers;

    protected string $tagName = "";

    public function getFromTags(array $tagsOnMethod, array $tagsOnClass = []): array
    {
        $fields = [];

        foreach ($tagsOnClass as $tag) {
            if (strtolower($tag->getName()) !== strtolower($this->tagName)) continue;

            $fieldData = $this->parseTag(trim($tag->getContent()));
            $fields[$fieldData['name']] = $fieldData;
        }

        foreach ($tagsOnMethod as $tag) {
            if (strtolower($tag->getName()) !== strtolower($this->tagName)) continue;

            $fieldData = $this->parseTag(trim($tag->getContent()));
            $fields[$fieldData['name']] = $fieldData;
        }

        return $fields;
    }

    abstract protected function parseTag(string $tagContent): array;

    protected function getDescriptionAndExample(
        string $description, string $type, string $tagContent, string $fieldName
    ): array
    {
        [$description, $example, $enumValues] = $this->parseExampleFromParamDescription($description, $type);
        $example = $this->setExampleIfNeeded($example, $type, $tagContent, $fieldName, $enumValues);
        return [$description, $example, $enumValues];
    }

    protected function setExampleIfNeeded(
        mixed $currentExample, string $type, string $tagContent, string $fieldName, ?array $enumValues = []
    ): mixed
    {
        return (is_null($currentExample) && !$this->shouldExcludeExample($tagContent))
            ? $this->generateDummyValue($type, hints: ['name' => $fieldName, 'enumValues' => $enumValues])
            : $currentExample;
    }
}
