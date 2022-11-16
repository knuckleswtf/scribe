<?php

namespace Knuckles\Scribe\Extracting;

use Faker\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait ParamHelpers
{

    protected function getFakeFactoryByName(string $name): ?\Closure
    {
        $faker = $this->getFaker();

        $name = strtolower(array_reverse(explode('.', $name))[0]);
        $normalizedName = match (true) {
            Str::endsWith($name, ['email', 'email_address']) => 'email',
            Str::endsWith($name, ['uuid']) => 'uuid',
            Str::endsWith($name, ['url']) => 'url',
            Str::endsWith($name, ['locale']) => 'locale',
            Str::endsWith($name, ['timezone']) => 'timezone',
            default => $name,
        };

        return match ($normalizedName) {
            'email' => fn() => $faker->safeEmail(),
            'password', 'pwd' => fn() => $faker->password(),
            'url' => fn() => $faker->url(),
            'description' => fn() => $faker->sentence(),
            'uuid' => fn() => $faker->uuid(),
            'locale' => fn() => $faker->locale(),
            'timezone' => fn() => $faker->timezone(),
            default => null,
        };
    }

    protected function getFaker(): \Faker\Generator
    {
        $faker = Factory::create();
        if ($seed = $this->config->get('examples.faker_seed')) {
            $faker->seed($seed);
        }
        return $faker;
    }

    protected function generateDummyValue(string $type, array $hints = [])
    {
        $fakeFactory = $this->getDummyValueGenerator($type, $hints);

        return $fakeFactory();
    }

    protected function getDummyValueGenerator(string $type, array $hints = []): \Closure
    {
        $baseType = $type;
        $isListType = false;

        if (Str::endsWith($type, '[]')) {
            $baseType = strtolower(substr($type, 0, strlen($type) - 2));
            $isListType = true;
        }

        $size = $hints['size'] ?? null;
        if ($isListType) {
            // Return a one-array item for a list by default.
            return $size
                ? fn() => [$this->generateDummyValue($baseType, range(0, min($size - 1, 5)))]
                : fn() => [$this->generateDummyValue($baseType, $hints)];
        }

        if (($hints['name'] ?? false) && $baseType != 'file') {
            $fakeFactoryByName = $this->getFakeFactoryByName($hints['name']);
            if ($fakeFactoryByName) return $fakeFactoryByName;
        }

        $faker = $this->getFaker();
        $min = $hints['min'] ?? null;
        $max = $hints['max'] ?? null;
        // If max and min were provided, the override size.
        $isExactSize = is_null($min) && is_null($max) && !is_null($size);

        $fakeFactoriesByType = [
            'integer' => function () use ($size, $isExactSize, $max, $faker, $min) {
                if ($isExactSize) return $size;
                return $max ? $faker->numberBetween((int)$min, (int)$max) : $faker->numberBetween(1, 20);
            },
            'number' => function () use ($size, $isExactSize, $max, $faker, $min) {
                if ($isExactSize) return $size;
                return $max ? $faker->numberBetween((int)$min, (int)$max) : $faker->randomFloat();
            },
            'boolean' => fn() => $faker->boolean(),
            'string' => fn() => $size ? $faker->lexify(str_repeat("?", $size)) : $faker->word(),
            'object' => fn() => [],
            'file' => fn() => UploadedFile::fake()->create('test.jpg')->size($size ?: 10),
        ];

        return $fakeFactoriesByType[$baseType] ?? $fakeFactoriesByType['string'];
    }

    private function getDummyDataGeneratorBetween(string $type, $min, $max = 90, string $fieldName = null): \Closure
    {
        $hints = [
            'name' => $fieldName,
            'size' => $this->getFaker()->numberBetween($min, $max),
            'min' => $min,
            'max' => $max,
        ];

        return $this->getDummyValueGenerator($type, $hints);
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
    public static function normalizeTypeName(?string $typeName, $value = null): string
    {
        if (!$typeName) {
            return 'string';
        }

        $base = str_replace('[]', '', strtolower($typeName));
        return match ($base) {
            'bool' => str_replace($base, 'boolean', $typeName),
            'int' => str_replace($base, 'integer', $typeName),
            'float', 'double' => str_replace($base, 'number', $typeName),
            'array' => (empty($value) || array_keys($value)[0] === 0)
                ? static::normalizeTypeName(gettype($value[0] ?? '')) . '[]'
                : 'object',
            default => $typeName
        };
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
