<?php

namespace Knuckles\Scribe\Tools;

class AnnotationParser
{
    /**
     * Parse an annotation like 'status=400 when="things go wrong" {"message": "failed"}'.
     * Attributes are always optional and may appear at the start or the end of the string.
     *
     * @param string $annotationContent
     */
    public static function parseIntoContentAndAttributes(string $annotationContent, array $allowedAttributes): array
    {
        $parsedAttributes = array_fill_keys($allowedAttributes, null);

        foreach ($allowedAttributes as $attribute) {
            preg_match("/$attribute=([^\\s'\"]+|\".+?\"|'.+?')\\s*/", $annotationContent, $attributeAndValue);

            if (count($attributeAndValue)) {
                [$matchingText, $attributeValue] = $attributeAndValue;
                $annotationContent = str_replace($matchingText, '', $annotationContent);

                $parsedAttributes[$attribute] = trim($attributeValue, '"\' ');
            }
        }

        return [
            'content' => trim($annotationContent),
            'attributes' => $parsedAttributes
        ];
    }
}
