<?php

namespace Knuckles\Scribe\Tools;

use Symfony\Component\VarExporter\VarExporter;

class WritingUtils
{
    /**
     * @param array $value
     * @param int $indentationLevel
     *
     * @return string
     * @throws \Symfony\Component\VarExporter\Exception\ExceptionInterface
     *
     */
    public static function printPhpArray(array $value, int $indentationLevel = 0): string
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
        foreach ($cleanQueryParams as $parameter => $value) {
            $paramName = urlencode($parameter);

            if (!is_array($value)) {
                $qs .= "$paramName=" . urlencode($value) . "&";
            } else {
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

    public static function printQueryParamsAsKeyValue(
        array $cleanQueryParams,
        string $quote = "\"",
        string $delimiter = ":",
        int $spacesIndentation = 4,
        string $braces = "{}",
        int $closingBraceIndentation = 0
    ): string {
        $output = "{$braces[0]}\n";
        foreach ($cleanQueryParams as $parameter => $value) {
            if (!is_array($value)) {
                $output .= str_repeat(" ", $spacesIndentation);
                $output .= "$quote$parameter$quote$delimiter $quote$value$quote,\n";
            } else {
                if (array_keys($value)[0] === 0) {
                    // List query param (eg filter[]=haha should become "filter[]": "haha")
                    $output .= str_repeat(" ", $spacesIndentation);
                    $output .= "$quote$parameter" . "[]$quote$delimiter $quote$value[0]$quote,\n";
                } else {
                    // Hash query param (eg filter[name]=john should become "filter[name]": "john")
                    foreach ($value as $item => $itemValue) {
                        $output .= str_repeat(" ", $spacesIndentation);
                        $output .= "$quote$parameter" . "[$item]$quote$delimiter $quote$itemValue$quote,\n";
                    }
                }
            }
        }

        return $output . str_repeat(" ", $closingBraceIndentation) . "{$braces[1]}";
    }

    public static $httpMethodToCssColour = [
        'GET' => 'green',
        'HEAD' => 'darkgreen',
        'POST' => 'black',
        'PUT' => 'darkblue',
        'PATCH' => 'purple',
        'DELETE' => 'red',
    ];

    /**
     * Convert a list of possible values to a friendly string:
     * [1, 2, 3] -> "1, 2, or 3"
     * [1, 2] -> "1 or 2"
     * [1] -> "1"
     * Each value is wrapped in HTML <code> tags, so you actually get "<code>1</code>, <code>2</code>, or <code>3</code>"
     *
     * @param array $list
     *
     * @return string
     */
    public static function getListOfValuesAsFriendlyHtmlString(array $list = []): string
    {
        switch (count($list)) {
            case 1:
                return "<code>{$list[0]}</code>";

            case 2:
                return "<code>{$list[0]}</code> or <code>{$list[1]}</code>";

            default:
                return "<code>"
                    . implode('</code>, <code>', array_slice($list, 0, -1))
                    . "</code>, or <code>" . end($list) . "</code>";
        }
    }
}
