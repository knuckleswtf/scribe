<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tools\WritingUtils;

class WritingUtilsTest extends BaseLaravelTest
{
    /** @test */
    public function print_query_params_as_key_value_js()
    {
        $queryParams = WritingUtils::printQueryParamsAsKeyValue($this->queryParams());
        $this->assertStringsEqualNormalizingNewlines(<<<EOL
                            {
                                "name query": "name value",
                                "list query[0]": "list element 1",
                                "list query[1]": "list element 2",
                                "nested query[nested query level 1 array][nested query level 2 list][0]": "nested level 2 list element 1",
                                "nested query[nested query level 1 array][nested query level 2 list][1]": "nested level 2 list element 2",
                                "nested query[nested query level 1 array][nested query level 2 query]": "name nested 2",
                                "nested query[nested query level 1 query]": "name nested 1",
                            }
                            EOL, $queryParams);

    }

    /** @test */
    public function print_query_params_as_key_value_php()
    {
        $queryParams = WritingUtils::printQueryParamsAsKeyValue($this->queryParams(), "'", " =>", 4, "[]");
        $this->assertStringsEqualNormalizingNewlines(<<<EOL
                            [
                                'name query' => 'name value',
                                'list query[0]' => 'list element 1',
                                'list query[1]' => 'list element 2',
                                'nested query[nested query level 1 array][nested query level 2 list][0]' => 'nested level 2 list element 1',
                                'nested query[nested query level 1 array][nested query level 2 list][1]' => 'nested level 2 list element 2',
                                'nested query[nested query level 1 array][nested query level 2 query]' => 'name nested 2',
                                'nested query[nested query level 1 query]' => 'name nested 1',
                            ]
                            EOL, $queryParams);

    }

    /** @test */
    public function print_query_params_as_key_value_python()
    {
        $queryParams = WritingUtils::printQueryParamsAsKeyValue($this->queryParams(), "'", ":", 2, "{}");
        $this->assertStringsEqualNormalizingNewlines(<<<EOL
                            {
                              'name query': 'name value',
                              'list query[0]': 'list element 1',
                              'list query[1]': 'list element 2',
                              'nested query[nested query level 1 array][nested query level 2 list][0]': 'nested level 2 list element 1',
                              'nested query[nested query level 1 array][nested query level 2 list][1]': 'nested level 2 list element 2',
                              'nested query[nested query level 1 array][nested query level 2 query]': 'name nested 2',
                              'nested query[nested query level 1 query]': 'name nested 1',
                            }
                            EOL, $queryParams);

    }

    /** @test */
    public function print_query_params_as_string_bash()
    {
        $queryParams = WritingUtils::printQueryParamsAsString($this->queryParams());

        $expected = implode('&', [
            'name+query=name+value',
            'list+query[]=list+element+1',
            'list+query[]=list+element+2',
            'nested+query[nested+query+level+1+array][nested+query+level+2+list][]=nested+level+2+list+element+1',
            'nested+query[nested+query+level+1+array][nested+query+level+2+list][]=nested+level+2+list+element+2',
            'nested+query[nested+query+level+1+array][nested+query+level+2+query]=name+nested+2',
            'nested+query[nested+query+level+1+query]=name+nested+1',
        ]);
        $this->assertEquals($expected, $queryParams);
    }

    /** @test */
    public function get_sample_body_with_array_fields()
    {
        $sampleBody = WritingUtils::getSampleBody($this->bodyParamsWithArrayFields());

        $expected = [
            'name' => 'Experience Form',
            'fields' => [
                [
                    'name' => 'experience',
                    'label' => 'Experience',
                    'type' => 'textarea',
                    'order' => 1,
                ],
            ],
        ];
        $this->assertEquals($expected, $sampleBody);
    }

    private function queryParams(): array
    {
        return [
            'name query' => 'name value',
            'list query' => [
                'list element 1',
                'list element 2',
            ],
            'nested query' => [
                'nested query level 1 array' => [
                    'nested query level 2 list' => [
                        'nested level 2 list element 1',
                        'nested level 2 list element 2',
                    ],
                    'nested query level 2 query' => 'name nested 2',
                ],
                'nested query level 1 query' => 'name nested 1'
            ],
        ];
    }

    private function bodyParamsWithArrayFields(): array
    {
        return [
            'name' => [
                'name' => 'name',
                'description' => 'Form\'s name',
                'required' => true,
                'example' => 'Experience Form',
                'type' => 'string',
                'custom' => [],
                '__fields' => [],
            ],
            'fields' => [
                'name' => 'fields',
                'description' => 'Form\'s fields',
                'required' => false,
                'example' => [[]],
                'type' => 'object[]',
                'custom' => [],
                '__fields' => [
                    'name' => [
                        'name' => 'fields[].name',
                        'description' => 'Field\'s name',
                        'required' => true,
                        'example' => 'experience',
                        'type' => 'string',
                        'custom' => [],
                    ],
                    'label' => [
                        'name' => 'fields[].label',
                        'description' => 'Field\'s label',
                        'required' => true,
                        'example' => 'Experience',
                        'type' => 'string',
                        'custom' => [],
                    ],
                    'type' => [
                        'name' => 'fields[].type',
                        'description' => 'Field\'s type',
                        'required' => true,
                        'example' => 'textarea',
                        'type' => 'string',
                        'custom' => [],
                    ],
                    'order' => [
                        'name' => 'fields[].order',
                        'description' => 'Field\'s order',
                        'required' => true,
                        'example' => 1,
                        'type' => 'number',
                        'custom' => [],
                    ],
                ],
            ],
        ];
    }

    protected function assertStringsEqualNormalizingNewlines(string $expected, string $actual)
    {
        $this->assertEquals(str_replace("\r", "", $expected), str_replace("\r", "", $actual));
    }
}
