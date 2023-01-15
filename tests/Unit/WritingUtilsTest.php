<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tools\WritingUtils;

class WritingUtilsTest extends BaseLaravelTest
{
    /** @test */
    public function print_query_params_as_key_value_js()
    {
        $queryParams = WritingUtils::printQueryParamsAsKeyValue($this->getQueryParams());
        $this->assertEquals(<<<EOL
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
        $queryParams = WritingUtils::printQueryParamsAsKeyValue($this->getQueryParams(), "'", "=>", 4, "[]");
        $this->assertEquals(<<<EOL
                            [
                                'name query'=> 'name value',
                                'list query[0]'=> 'list element 1',
                                'list query[1]'=> 'list element 2',
                                'nested query[nested query level 1 array][nested query level 2 list][0]'=> 'nested level 2 list element 1',
                                'nested query[nested query level 1 array][nested query level 2 list][1]'=> 'nested level 2 list element 2',
                                'nested query[nested query level 1 array][nested query level 2 query]'=> 'name nested 2',
                                'nested query[nested query level 1 query]'=> 'name nested 1',
                            ]
                            EOL, $queryParams);

    }

    /** @test */
    public function print_query_params_as_key_value_python()
    {
        $queryParams = WritingUtils::printQueryParamsAsKeyValue($this->getQueryParams(), "'", ":", 2, "{}");
        $this->assertEquals(<<<EOL
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

    private function getQueryParams(): array
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
}
