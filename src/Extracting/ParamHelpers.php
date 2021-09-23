<?php

namespace Knuckles\Scribe\Extracting;

use Faker\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait ParamHelpers
{

    protected function getFaker(): \Faker\Generator
    {
        $faker = Factory::create();
        if ($this->config->get('faker_seed')) {
            $faker->seed($this->config->get('faker_seed'));
        }
        return $faker;
    }

    protected function generateDummyValue(string $type, int $size = null)
    {
        $fakeFactory = $this->getDummyValueGenerator($type, $size);

        return $fakeFactory();
    }

    protected function getDummyValueGenerator(string $type, int $size = null): \Closure
    {
        $baseType = $type;
        $isListType = false;

        if (Str::endsWith($type, '[]')) {
            $baseType = strtolower(substr($type, 0, strlen($type) - 2));
            $isListType = true;
        }

        if ($isListType) {
            // Return a one-array item for a list.
            return fn() => [$this->generateDummyValue($baseType)];
        }

        $faker = $this->getFaker();

        $fakeFactories = [
            'integer' => fn() => $size ?: $faker->numberBetween(1, 20),
            'number' => fn() => $size ?: $faker->randomFloat(),
            'boolean' => fn() => $faker->boolean(),
            'string' => fn() => $size ? $faker->lexify(str_repeat("?", $size)) : $faker->word,
            'object' => fn() => [],
            'file' => fn() => UploadedFile::fake()->create('test.jpg')->size($size ?: 10),
        ];

        return $fakeFactories[$baseType] ?? $fakeFactories['string'];
    }

    private function getDummyDataGeneratorBetween(string $type, $min, $max = null): \Closure
    {
        $baseType = $type;
        $isListType = false;

        if (Str::endsWith($type, '[]')) {
            $baseType = strtolower(substr($type, 0, strlen($type) - 2));
            $isListType = true;
        }

        $randomSize = $this->getFaker()->numberBetween($min, $max);

        if ($isListType) {
            return fn() => array_map(
                fn() => $this->generateDummyValue($baseType),
                range(0, $randomSize - 1)
            );
        }

        $faker = $this->getFaker();

        $fakeFactories = [
            'integer' => fn() => $faker->numberBetween((int)$min, (int)$max),
            'number' => fn() => $faker->numberBetween((int)$min, (int)$max),
            'string' => fn() => $faker->lexify(str_repeat("?", $randomSize)),
            'file' => fn() => UploadedFile::fake()->create('test.jpg')->size($randomSize),
        ];

        return $fakeFactories[$baseType] ?? $fakeFactories['string'];
    }

    protected function isSupportedTypeInDocBlocks(string $type): bool
    {
        $types = [
            'integer',
            'int',
            'number',
            'float',
            'double',
            'boolean',
            'bool',
            'string',
            'object',
        ];
        return in_array(str_replace('[]', '', $type), $types);
    }

    /**
     * Cast a value to a specified type.
     *
     * @param mixed $value
     * @param string $type
     *
     * @return mixed
     */
    protected function castToType($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        if ($type === "array") {
            $type = "string[]";
        }

        if (Str::endsWith($type, '[]')) {
            $baseType = strtolower(substr($type, 0, strlen($type) - 2));
            return is_array($value) ? array_map(function ($v) use ($baseType) {
                return $this->castToType($v, $baseType);
            }, $value) : json_decode($value);
        }

        if ($type === 'object') {
            return is_array($value) ? $value : json_decode($value, true);
        }

        $casts = [
            'integer' => 'intval',
            'int' => 'intval',
            'float' => 'floatval',
            'number' => 'floatval',
            'double' => 'floatval',
            'boolean' => 'boolval',
            'bool' => 'boolval',
        ];

        // First, we handle booleans. We can't use a regular cast,
        // because PHP considers string 'false' as true.
        if ($value == 'false' && ($type == 'boolean' || $type == 'bool')) {
            return false;
        }

        if (isset($casts[$type])) {
            return $casts[$type]($value);
        }

        // Return the value unchanged if there's no applicable cast
        return $value;
    }

    /**
     * Normalizes the stated "type" of a parameter (eg "int", "integer", "double", "array"...)
     * to a number of standard JSON types (integer, boolean, number, object...).
     * Will return the input if no match.
     *
     * @param string|null $typeName
     * @param mixed $value
     *
     * @return string
     */
    protected function normalizeTypeName(?string $typeName, $value = null): string
    {
        if (!$typeName) {
            return 'string';
        }

        $base = str_replace('[]', '', strtolower($typeName));
        switch ($base) {
            case 'int':
                return str_replace($base, 'integer', $typeName);
            case 'float':
            case 'double':
                return str_replace($base, 'number', $typeName);
            case 'bool':
                return str_replace($base, 'boolean', $typeName);
            case 'array':
                if (empty($value) || array_keys($value)[0] === 0) {
                    return $this->normalizeTypeName(gettype($value[0] ?? '')).'[]';
                } else {
                    return 'object';
                }
            default:
                return $typeName;
        }
    }

    /**
     * Allows users to specify that we shouldn't generate an example for the parameter
     * by writing 'No-example'.
     *
     * @param string $description
     *
     * @return bool If true, don't generate an example for this.
     */
    protected function shouldExcludeExample(string $description): bool
    {
        return strpos($description, ' No-example') !== false;
    }

    /**
     * Allows users to specify an example for the parameter by writing 'Example: the-example',
     * to be used in example requests and response calls.
     *
     * @param string $description
     * @param string $type The type of the parameter. Used to cast the example provided, if any.
     *
     * @return array The description and included example.
     */
    protected function parseExampleFromParamDescription(string $description, string $type): array
    {
        $example = null;
        if (preg_match('/(.*)\bExample:\s*([\s\S]+)\s*/s', $description, $content)) {
            $description = trim($content[1]);

            // Examples are parsed as strings by default, we need to cast them properly
            $example = $this->castToType($content[2], $type);
        }

        return [$description, $example];
    }
}
