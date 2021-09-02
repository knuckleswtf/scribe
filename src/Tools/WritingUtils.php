<?php

namespace Knuckles\Scribe\Tools;

use Symfony\Component\VarExporter\VarExporter;

class WritingUtils
{

    public static array $httpMethodToCssColour = [
        'GET' => 'green',
        'HEAD' => 'darkgreen',
        'POST' => 'black',
        'PUT' => 'darkblue',
        'PATCH' => 'purple',
        'DELETE' => 'red',
        'OPTIONS' => 'grey',
    ];

    /**
     * Print a value as valid PHP, handling arrays and proper indentation.
     *
     * @param mixed $value
     * @param int $indentationLevel
     *
     * @return string
     * @throws \Symfony\Component\VarExporter\Exception\ExceptionInterface
     *
     */
    public static function printPhpValue($value, int $indentationLevel = 0): string
    {
        $output = VarExporter::export($value);
        // Padding with x spaces so they align
        $split = explode("\n", $output);
        $result = '';
        $padWith = str_repeat(' ', $indentationLevel);
        foreach ($split as $index => $line) {
            $result .= ($index == 0 ? '' : "\n$padWith") . $line;
        }

        return $result;
    }

    public static function printQueryParamsAsString(array $cleanQueryParams): string
    {
        $qs = '';
        foreach ($cleanQueryParams as $paramName => $value) {
            if (!is_array($value)) {
                $qs .= "$paramName=" . urlencode($value) . "&";
            } else {
                if (count($value) == 0) {
                    continue;
                }
                if (array_keys($value)[0] === 0) {
                    // List query param (eg filter[]=haha should become "filter[]": "haha")
                    $qs .= "$paramName" . '[]=' . urlencode($value[0]) . '&';
                } else {
                    // Hash query param (eg filter[name]=john should become "filter[name]": "john")
                    foreach ($value as $item => $itemValue) {
                        $qs .= "$paramName" . '[' . urlencode($item) . ']=' . urlencode($itemValue) . '&';
                    }
                }
            }
        }

        return rtrim($qs, '&');
    }

    /**
     * Print key-value query params as a hash { "key1": "value1", "key2": "value2" }
     * Supports custom delimiters (eg "=>", default: ":"),
     *   custom braces (eg "[]", default: "{}"),
     *   custom quotes (eg ', default: "),
     *   custom indentation, line endings etc.
     * Expands/simplifies arrays {key: [1, 2,]} becomes {"key[]": "1"}
     * Expands hashes {key: {a: 1, b: 2}} becomes {"key[a]": "1", "key[b]": "2"}
     *
     * @param array $cleanQueryParams
     * @param string $quote
     * @param string $delimiter
     * @param int $spacesIndentation
     * @param string $braces
     * @param int $closingBraceIndentation
     * @param string $startLinesWith
     * @param string $endLinesWith
     *
     * @return string
     */
    public static function printQueryParamsAsKeyValue(
        array $cleanQueryParams,
        string $quote = '"',
        string $delimiter = ":",
        int $spacesIndentation = 4,
        string $braces = "{}",
        int $closingBraceIndentation = 0,
        string $startLinesWith = '',
        string $endLinesWith = ','
    ): string
    {
        $output = isset($braces[0]) ? "{$braces[0]}\n" : '';
        foreach ($cleanQueryParams as $parameter => $value) {
            if (!is_array($value)) {
                $output .= str_repeat(" ", $spacesIndentation);
                // Example: -----"param_name": "value"----
                $formattedValue = gettype($value) === "boolean" ? ($value ? 1 : 0) : $value;
                $output .= "$startLinesWith$quote$parameter$quote$delimiter $quote$formattedValue$quote$endLinesWith\n";
            } else {
                if (count($value) == 0) {
                    continue;
                }
                if (array_keys($value)[0] === 0) {
                    // List query param (eg filter[]=haha should become "filter[]": "haha")
                    $output .= str_repeat(" ", $spacesIndentation);
                    $output .= "$startLinesWith$quote$parameter" . "[]$quote$delimiter $quote$value[0]$quote$endLinesWith\n";
                } else {
                    // Hash query param (eg filter[name]=john should become "filter[name]": "john")
                    foreach ($value as $item => $itemValue) {
                        $output .= str_repeat(" ", $spacesIndentation);
                        $output .= "$startLinesWith$quote$parameter" . "[$item]$quote$delimiter $quote$itemValue$quote$endLinesWith\n";
                    }
                }
            }
        }

        $closing = isset($braces[1]) ? str_repeat(" ", $closingBraceIndentation) . "{$braces[1]}" : '';
        return $output . $closing;
    }

    /**
     * Expand a request parameter into one or more parameters to be used when sending as form-data.
     * A primitive value like ("name", "John") is returned as ["name" => "John"]
     * Lists like ("filter", ["haha"]) becomes ["filter[]" => "haha"]
     * Maps like ("filter", ["name" => "john", "age" => "12"]) become ["filter[name]" => "john", "filter[age]" => 12]
     *
     * @param string $parameter The name of the parameter
     * @param mixed $value Value of the parameter
     *
     * @return array
     */
    public static function getParameterNamesAndValuesForFormData(string $parameter, $value): array
    {
        if (!is_array($value)) {
            return [$parameter => $value];
        }

        // We assume it's a list if its first key is 0
        $keys = array_keys($value);
        if (count($keys) && $keys[0] === 0) {
            if (is_array($value[0])) {
                // handle nested arrays/objects
                $params = [];
                $expanded = self::getParameterNamesAndValuesForFormData('', $value[0]);
                foreach ($expanded as $fieldName => $itemValue) {
                    $paramName = $parameter.'[]'.$fieldName;
                    $params[$paramName] = $itemValue;
                }
                return $params;
            }
            return [$parameter.'[]' => $value[0]];
        }

        // Transform hashes
        $params = [];
        foreach ($value as $item => $itemValue) {
            if (is_array($itemValue)) {
                $expanded = self::getParameterNamesAndValuesForFormData('', $itemValue);
                foreach ($expanded as $fieldName => $subItemValue) {
                    $paramName = $parameter . "[$item]".$fieldName;
                    $params[$paramName] = $subItemValue;
                }
            } else {
                $params[$parameter . "[$item]"] = $itemValue;
            }
        }
        return $params;
    }

    /**
     * Convert a list of possible values to a friendly string:
     * [1, 2, 3] -> "1, 2, or 3"
     * [1, 2] -> "1 or 2"
     * [1] -> "1"
     * Each value is wrapped in HTML <code> tags, so you actually get "<code>1</code>, <code>2</code>, or
     * <code>3</code>"
     *
     * @param array $list
     *
     * @return string
     */
    public static function getListOfValuesAsFriendlyHtmlString(array $list = [], string $conjunction = "or"): string
    {
        switch (count($list)) {
            case 1:
                return "<code>{$list[0]}</code>";

            case 2:
                return "<code>{$list[0]}</code> $conjunction <code>{$list[1]}</code>";

            default:
                return "<code>"
                    . implode('</code>, <code>', array_slice($list, 0, -1))
                    . "</code>, $conjunction <code>" . end($list) . "</code>";
        }
    }

    /**
     * Convert a path like 'js/tryitout.js' to include the current Scribe version ('js/tryitout-3.0.1.js')
     */
    public static function getVersionedAsset(string $assetPath): string
    {
        $index = strrpos($assetPath, ".");
        return substr_replace($assetPath, '-'.Globals::SCRIBE_VERSION, $index, 0);
    }
}
