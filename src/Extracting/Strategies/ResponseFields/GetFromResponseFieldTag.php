<?php

namespace Knuckles\Scribe\Extracting\Strategies\ResponseFields;

use Knuckles\Scribe\Extracting\Shared\ResponseFieldTools;
use Knuckles\Scribe\Extracting\Strategies\GetFieldsFromTagStrategy;

class GetFromResponseFieldTag extends GetFieldsFromTagStrategy
{
    protected string $tagName = 'responseField';

    protected function parseTag(string $tagContent): array
    {
        // Format:
        // @responseField <name> <type> <description>
        // Examples:
        // @responseField text string The text.
        // @responseField user_id integer The ID of the user.
        preg_match('/(.+?)\s+(.+?)\s+([\s\S]*)/', $tagContent, $content);
        if (empty($content)) {
            // This means only name and type were supplied
            [$name, $type] = preg_split('/\s+/', $tagContent);
            $description = '';
        } else {
            [$_, $name, $type, $description] = $content;
            $description = trim($description);
        }

        $type = static::normalizeTypeName($type);
        $data = compact('name', 'type', 'description');

        // Support optional type in annotation
        // The type can also be a union or nullable type (eg ?string or string|null)
        if (!$this->isSupportedTypeInDocBlocks(explode('|', trim($type, '?'))[0])) {
            // Then that wasn't a type, but part of the description
            $data['description'] = trim("$type $description");
            $data['type'] = '';

            $data['type'] = ResponseFieldTools::inferTypeOfResponseField($data, $this->endpointData);
        }

        return $data;
    }
}
