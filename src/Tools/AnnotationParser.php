<?php

namespace Knuckles\Scribe\Tools;

use Closure;
use Exception;
use Illuminate\Routing\Route;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarExporter\VarExporter;

class AnnotationParser
{
    /**
     * Parse an annotation like 'status=400 when="things go wrong" {"message": "failed"}'.
     * Attributes are always optional.
     *
     * @param string $annotationContent
     */
    public static function parseIntoContentAndAttributes(string $annotationContent, array $allowedAttributes): array
    {
        $result = preg_split('/(\w+)=(\w+|".+?"|\'.+?\')\s*/', trim($annotationContent), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $defaults = array_fill_keys($allowedAttributes, null);
        if (count($result) == 1) { // No key-value pairs
            return [
                'content' => trim($result[0]),
                'attributes' => $defaults];
        }

        // Separate the main content
        if (in_array($result[0], $allowedAttributes)) {
            $content = trim(array_pop($result));
        } else {
            $content = trim(array_shift($result));
        }

        [$keys, $values] = collect($result)->partition(function ($value, $key) {
            return $key % 2;
        });
        $attributes = collect($values)->combine($keys)
            ->map(function ($value) {
                return trim($value, '"\' ');
            })
            ->toArray();
        $attributes = array_merge($defaults, $attributes);

        return compact('content', 'attributes');
    }
}
