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
        // @responseField <name> <type> <"required" (optional)> <description>
        // Examples:
        // @responseField text string required The text.
        // @responseField user_id integer The ID of the user.
        preg_match('/(.+?)\s+(.+?)\s+(.+?)\s+([\s\S]*)/', $tagContent, $content);
        if (empty($content)) {
            // This means only name and type were supplied
            [$name, $type] = preg_split('/\s+/', $tagContent);
            $description = '';
            $required = false;
        } else {
            [$_, $name, $type, $required, $description] = $content;
            if($required !== "required"){
                $description = $required . " " . $description;
            }

            $required = $required === "required";
            $description = trim($description);
        }

        $type = static::normalizeTypeName($type);
        $data = compact('name', 'type', 'required', 'description');

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
        $nonApiResourceFields = parent::getFromTags($tagsOnMethod, $tagsOnClass);
        $apiResourceFields = $this->getApiResourceFields($tagsOnMethod);

        return [...$nonApiResourceFields, ...$apiResourceFields];
    }

    protected function getApiResourceFields(array $tagsOnMethod): array
    {
        $apiResourceClassName = $this->getApiResourceClassName($tagsOnMethod);

        if (empty($apiResourceClassName)) {
            return [];
        }

        return $this->extractFieldsFromApiResource($apiResourceClassName);
    }

    protected function getApiResourceClassName(array $tagsOnMethod): ?string
    {
        $apiResourceTags = array_values(
            array_filter($tagsOnMethod, function ($tag) {
                return in_array(strtolower($tag->getName()), ['apiresource', 'apiresourcecollection']);
            })
        );

        if (empty($apiResourceTags)) {
            return null;
        }

        return $this->getClassNameFromApiResourceTag($apiResourceTags[0]->getContent());
    }

    protected function extractFieldsFromApiResource(string $className): array
    {
        $method = u::getReflectedRouteMethod([$className, 'toArray']);
        $docBlock = new DocBlock($method->getDocComment() ?: '');
        $tagsOnApiResource = $docBlock->getTags();

        $wrapKey = $className::$wrap ?? null;
        $fields = parent::getFromTags($tagsOnApiResource, []);

        return $this->applyWrapKeyPrefix($fields, $wrapKey);
    }

    protected function applyWrapKeyPrefix(array $fields, ?string $wrapKey): array
    {
        if ($wrapKey === null) {
            return $fields;
        }

        $wrappedFields = [];
        foreach ($fields as $fieldName => $fieldData) {
            $fieldData['name'] = $wrapKey . '.' . $fieldData['name'];
            $wrappedFields[$fieldData['name']] = $fieldData;
        }

        return $wrappedFields;
    }

    public function getClassNameFromApiResourceTag(string $apiResourceTag): string
    {
        ['content' => $className] = a::parseIntoContentAndFields($apiResourceTag, UseApiResourceTags::apiResourceAllowedFields());
        return $className;
    }
}
