<?php

namespace Knuckles\Scribe\Tools;

class AnnotationParser
{
    /**
     * Parse an annotation like 'status=400 when="things go wrong" {"message": "failed"}'.
     * Fields are always optional and may appear at the start or the end of the string.
     *
     * @param string $annotationContent
     * @param array $allowedFields List of fields to look for.
     *
     * @return array{content: string, fields: string[]}
     */
    public static function parseIntoContentAndFields(string $annotationContent, array $allowedFields): array
    {
        $parsedFields = array_fill_keys($allowedFields, null);

        foreach ($allowedFields as $field) {
            preg_match("/$field=([^\\s'\"]+|\".+?\"|'.+?')\\s*/", $annotationContent, $fieldAndValue);

            if (count($fieldAndValue)) {
                [$matchingText, $attributeValue] = $fieldAndValue;
                $annotationContent = str_replace($matchingText, '', $annotationContent);

                $parsedFields[$field] = trim($attributeValue, '"\' ');
            }
        }

        return [
            'content' => trim($annotationContent),
            'fields' => $parsedFields
        ];
    }

    /**
     * Parse an annotation like 'title=This message="everything good"' into a key-value array.
     * All non key-value fields will be ignored. Useful for `@apiResourceAdditional`,
     * where users may specify arbitrary fields.
     *
     * @param string $annotationContent
     * @return array
     */
    public static function parseIntoFields(string $annotationContent): array
    {
        $fields = $matches = [];

        preg_match_all(
            '/([^\s\'"]+|".+?"|\'.+?\')=([^\s\'"]+|".+?"|\'.+?\')/',
            $annotationContent,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $fields[trim($match[1], '"\' ')] = trim($match[2], '"\' ');
        }

        return $fields;
    }
}
