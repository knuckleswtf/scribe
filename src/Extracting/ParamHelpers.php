<?php

namespace Knuckles\Scribe\Extracting;

use Faker\Factory;
use stdClass;

trait ParamHelpers
{

    protected function getFaker()
    {
        $faker = Factory::create();
        if ($this->config->get('faker_seed')) {
            $faker->seed($this->config->get('faker_seed'));
        }
        return $faker;
    }

    protected function generateDummyValue(string $type)
    {
        $faker = $this->getFaker();

        $fakeFactories = [
            'integer' => function () use ($faker) {
                return $faker->numberBetween(1, 20);
            },
            'number' => function () use ($faker) {
                return $faker->randomFloat();
            },
            'float' => function () use ($faker) {
                return $faker->randomFloat();
            },
            'boolean' => function () use ($faker) {
                return $faker->boolean();
            },
            'string' => function () use ($faker) {
                return $faker->word;
            },
            'array' => function () {
                return [];
            },
            'object' => function () {
                return new stdClass();
            },
        ];

        $fakeFactory = $fakeFactories[$type] ?? $fakeFactories['string'];

        return $fakeFactory();
    }

    protected function isSupportedTypeInDocBlocks(string $type)
    {
        $types = [
            'integer',
            'int',
            'float',
            'number',
            'double',
            'boolean',
            'bool',
            'string',
            'list',
            'array',
            'object',
        ];
        return in_array($type, $types);
    }

    /**
     * Cast a value from a string to a specified type.
     *
     * @param $value
     * @param string $type
     *
     * @return mixed
     */
    protected function castToType($value, string $type)
    {
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
        //because PHP considers string 'false' as true.
        if ($value == 'false' && ($type == 'boolean' || $type == 'bool')) {
            return false;
        }

        if (isset($casts[$type])) {
            return $casts[$type]($value);
        }

        return $value;
    }

    /**
     * Normalizes the stated "type" of a parameter (eg "int", "integer", "double")
     * to a number of standard types (integer, boolean, float). Will return the input if no match.
     *
     * @param string $type
     *
     * @return string
     */
    protected function normalizeParameterType(string $type): string
    {
        $typeMap = [
            'int' => 'integer',
            'bool' => 'boolean',
            'double' => 'float',
        ];

        return $type ? ($typeMap[$type] ?? $type) : 'string';
    }

    /**
     * Allows users to specify that we shouldn't generate an example for the parameter
     * by writing 'No-example'.
     *
     * @param string $description
     *
     * @return bool If true, don't generate an example for this.
     */
    protected function shouldExcludeExample(string $description)
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
    protected function parseParamDescription(string $description, string $type)
    {
        $example = null;
        if (preg_match('/(.*)\bExample:\s*(.+)\s*/', $description, $content)) {
            $description = trim($content[1]);

            // examples are parsed as strings by default, we need to cast them properly
            $example = $this->castToType($content[2], $type);
        }

        return [$description, $example];
    }
}
