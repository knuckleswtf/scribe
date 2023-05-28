<?php

namespace Knuckles\Scribe\Extracting\Strategies\ResponseFields;

use Knuckles\Scribe\Extracting\Shared\ResponseFieldTools;
use Knuckles\Scribe\Extracting\Strategies\GetFieldsFromTagStrategy;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Mpociot\Reflection\DocBlock;
use Knuckles\Scribe\Tools\Utils as u;

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

    /**
     * Get responseField tags from the controller method or the API resource class.
     */
    public function getFromTags(array $tagsOnMethod, array $tagsOnClass = []): array
    {
        $apiResourceTags = array_values(
            array_filter($tagsOnMethod, function ($tag) {
                return in_array(strtolower($tag->getName()), ['apiresource', 'apiresourcecollection']);
            })
        );

        if (!empty($apiResourceTags) &&
            !empty($className = $this->getClassNameFromApiResourceTag($apiResourceTags[0]->getContent()))
        ) {
            $method = u::getReflectedRouteMethod([$className, 'toArray']);
            $docBlock = new DocBlock($method->getDocComment() ?: '');
            $tagsOnApiResource = $docBlock->getTags();
        }

        return parent::getFromTags(array_merge($tagsOnMethod, $tagsOnApiResource ?? []), $tagsOnClass);
    }

    public function getClassNameFromApiResourceTag(string $apiResourceTag): string
    {
        ['content' => $className] = a::parseIntoContentAndFields($apiResourceTag, UseApiResourceTags::apiResourceAllowedFields());
        return $className;
    }
}
