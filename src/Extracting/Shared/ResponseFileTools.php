<?php

namespace Knuckles\Scribe\Extracting\Shared;

class ResponseFileTools
{
    public static function getResponseContents($filePath, array|string|null $merge): string
    {
        $content = self::getFileContents($filePath);
        if (empty($merge)) {
            return $content;
        }

        if (is_string($merge)) {
            $json = str_replace("'", '"', $merge);
            return json_encode(array_merge(json_decode($content, true), json_decode($json, true)));
        }

        if (is_array($merge)) {
            return json_encode(array_merge(json_decode($content, true), $merge));
        }
    }

    protected static function getFileContents($filePath): string
    {
        if (!file_exists($filePath)) {
            // Try Laravel storage folder
            if (!file_exists(storage_path($filePath))) {
                throw new \InvalidArgumentException("@responseFile {$filePath} does not exist");
            }

            $filePath = storage_path($filePath);
        }
        return file_get_contents($filePath, true);
    }
}
