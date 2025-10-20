<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Scribe\Extracting\Strategies\GetFieldsFromTagStrategy;

class GetFromBodyParamTag extends GetFieldsFromTagStrategy
{
    protected string $tagName = "bodyParam";

    public function parseTag(string $tagContent): array
    {
        // Format:
        // @bodyParam <name> <type> <"required" (optional)> <"deprecated" (optional)> <description>
        // Examples:
        // @bodyParam text string required The text.
        // @bodyParam user_id integer The ID of the user.
        // @bodyParam status string required deprecated Use `is_active` instead.
        preg_match('/(.+?)\s+(.+?)\s+(required\s+)?(deprecated\s+)?([\s\S]*)/', $tagContent, $parsedContent);

        if (empty($parsedContent)) {
            // This means only name and type were supplied
            [$name, $type] = preg_split('/\s+/', $tagContent);
            $required = false;
            $deprecated = false;
            $description = '';
        } else {
            [$_, $name, $type, $required, $deprecated, $description] = $parsedContent;
            $description = trim(str_replace(['No-example.', 'No-example'], '', $description));
            if ($description == 'required') {
                $required = $description;
                $description = '';
            } elseif ($description == 'deprecated') {
                $deprecated = $description;
                $description = '';
            }
            $required = trim($required) === 'required';
            $deprecated = trim($deprecated) === 'deprecated';
        }

        $type = static::normalizeTypeName($type);
        [$description, $example, $enumValues, $exampleWasSpecified] =
            $this->getDescriptionAndExample($description, $type, $tagContent, $name);

        return compact('name', 'type', 'description', 'required', 'deprecated', 'example', 'enumValues', 'exampleWasSpecified');
    }
}
