<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Scribe\Extracting\Strategies\GetFieldsFromTagStrategy;

class GetFromBodyParamTag extends GetFieldsFromTagStrategy
{
    protected string $tagName = "bodyParam";

    public function parseTag(string $tagContent): array
    {
        // Format:
        // @bodyParam <name> <type> <"required" (optional)> <description>
        // Examples:
        // @bodyParam text string required The text.
        // @bodyParam user_id integer The ID of the user.
        preg_match('/(.+?)\s+(.+?)\s+(required\s+)?([\s\S]*)/', $tagContent, $parsedContent);

        if (empty($parsedContent)) {
            // This means only name and type were supplied
            [$name, $type] = preg_split('/\s+/', $tagContent);
            $required = false;
            $description = '';
        } else {
            [$_, $name, $type, $required, $description] = $parsedContent;
            $description = trim(str_replace(['No-example.', 'No-example'], '', $description));
            if ($description == 'required') {
                $required = $description;
                $description = '';
            }
            $required = trim($required) === 'required';
        }

        $type = static::normalizeTypeName($type);
        [$description, $example, $enumValues] =
            $this->getDescriptionAndExample($description, $type, $tagContent, $name);

        return compact('name', 'type', 'description', 'required', 'example', 'enumValues');
    }
}
