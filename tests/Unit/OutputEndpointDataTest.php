<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Camel\Extraction\Parameter;
use Knuckles\Camel\Extraction\ResponseField;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tests\BaseUnitTest;

class OutputEndpointDataTest extends BaseUnitTest
{
    /** @test */
    public function can_nest_array_and_object_parameters_correctly()
    {
        $parameters = [
            "dad" => Parameter::create([
                "name" => 'dad',
            ]),
            "dad.age" => ResponseField::create([
                "name" => 'dad.age',
            ]),
            "dad.cars[]" => Parameter::create([
                "name" => 'dad.cars[]',
            ]),
            "dad.cars[].model" => Parameter::create([
                "name" => 'dad.cars[].model',
            ]),
            "dad.cars[].price" => ResponseField::create([
                "name" => 'dad.cars[].price',
            ]),
        ];
        $cleanParameters = [];

        $nested = OutputEndpointData::nestArrayAndObjectFields($parameters, $cleanParameters);

        $this->assertEquals(["dad"], array_keys($nested));
        $this->assertArraySubset([
            "dad" => [
                "name" => "dad",
                "__fields" => [
                    "age" => [
                        "name" => "dad.age",
                    ],
                    "cars" => [
                        "name" => "dad.cars",
                        "__fields" => [
                            "model" => [
                                "name" => "dad.cars[].model",
                            ],
                            "price" => [
                                "name" => "dad.cars[].price",
                            ],
                        ],
                    ],
                ],
            ],
        ], $nested);
    }

    /** @test */
    public function sets_missing_ancestors_for_object_fields_properly()
    {
        $parameters = [
            "dad.cars[]" => Parameter::create([
                "name" => 'dad.cars[]',
            ]),
            "dad.cars[].model" => Parameter::create([
                "name" => 'dad.cars[].model',
            ]),
            "parent.not.specified" => Parameter::create([
                "name" => "parent.not.specified",
            ]),
        ];
        $cleanParameters = [];

        $nested = OutputEndpointData::nestArrayAndObjectFields($parameters, $cleanParameters);

        $this->assertEquals(["dad", "parent"], array_keys($nested));
        $this->assertArraySubset([
            "dad" => [
                "name" => "dad",
                "__fields" => [
                    "cars" => [
                        "name" => "dad.cars",
                        "__fields" => [
                            "model" => [
                                "name" => "dad.cars[].model",
                            ],
                        ],
                    ],
                ],
            ],
            "parent" => [
                "name" => "parent",
                "__fields" => [
                    "not" => [
                        "name" => "parent.not",
                        "__fields" => [
                            "specified" => [
                                "name" => "parent.not.specified",
                            ],
                        ],
                    ],
                ],
            ],
        ], $nested);
    }
}
