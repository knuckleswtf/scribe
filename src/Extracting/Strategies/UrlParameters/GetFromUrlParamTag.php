<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\Strategies\GetFieldsFromTagStrategy;

class GetFromUrlParamTag extends GetFieldsFromTagStrategy
{
    protected string $tagName = "urlParam";

    protected function parseTag(string $tagContent): array
    {
        // Format:
        // @urlParam <name> <type (optional)> <"required" (optional)> <description>
        // Examples:
        // @urlParam id string required The id of the post.
        // @urlParam user_id The ID of the user.

        // We match on all the possible types for URL parameters. It's a limited range, so no biggie.
        preg_match('/(\w+?)\s+((int|integer|string|float|double|number)\s+)?(required\s+)?([\s\S]*)/', $tagContent, $content);
        if (empty($content)) {
            // This means only name was supplied
            $name = $tagContent;
            $required = false;
            $description = '';
            $type = 'string';
        } else {
            [$_, $name, $__, $type, $required, $description] = $content;
            $description = trim(str_replace(['No-example.', 'No-example'], '', $description));
            if ($description === 'required') {
                $required = true;
                $description = '';
            } else {
                $required = trim($required) === 'required';
            }

            if (empty($type) && $this->isSupportedTypeInDocBlocks($description)) {
                // Only type was supplied
                $type = $description;
                $description = '';
            }

            $type = empty($type)
                ? (Str::contains($description, ['number', 'count', 'page']) ? 'integer' : 'string')
                : static::normalizeTypeName($type);
        }

        [$description, $example, $enumValues] =
            $this->getDescriptionAndExample($description, $type, $tagContent, $name);

        return compact('name', 'description', 'required', 'example', 'type', 'enumValues');
    }
}
