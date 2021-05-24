<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tools\DocumentationConfig;

class ValidationRuleParsingTest extends BaseLaravelTest
{
    private $strategy;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->strategy = new class {
            use ParsesValidationRules;

            public function parse($validationRules, $customParameterData = []): array
            {
                $this->config = new DocumentationConfig([]);
                $bodyParametersFromValidationRules = $this->getBodyParametersFromValidationRules($validationRules, $customParameterData);
                return $this->normaliseArrayAndObjectParameters($bodyParametersFromValidationRules);
            }
        };
    }

    /**
     * @test
     * @dataProvider supportedRules
     */
    public function can_parse_supported_rules(array $ruleset, array $customInfo, array $expected)
    {
        $results = $this->strategy->parse($ruleset, $customInfo);

        $parameterName = array_keys($ruleset)[0];
        ray($parameterName, $results[$parameterName]);

        $this->assertEquals($expected['type'], $results[$parameterName]['type']);
        $this->assertStringEndsWith($expected['description'], $results[$parameterName]['description']);

        // Validate that the generated values actually pass validation
        $validator = Validator::make([$parameterName => $results[$parameterName]['example']], $ruleset);
        try {
            $validator->validate();
        } catch (ValidationException $e) {
            dump('Value: ', $results[$parameterName]['example']);
            dump($e->errors());
            throw $e;
        }
    }


    /** @test */
    public function can_transform_arrays_and_objects()
    {
        $ruleset = [
            'array_param' => 'array|required',
            'array_param.*' => 'string',
        ];
        $results = $this->strategy->parse($ruleset);
        $this->assertCount(1, $results);
        $this->assertEquals('string[]', $results['array_param']['type']);

        $ruleset = [
            'object_param' => 'array|required',
            'object_param.field1.*' => 'string',
            'object_param.field2' => 'integer|required',
        ];
        $results = $this->strategy->parse($ruleset);
        $this->assertCount(3, $results);
        $this->assertEquals('object', $results['object_param']['type']);
        $this->assertEquals('string[]', $results['object_param.field1']['type']);
        $this->assertEquals('integer', $results['object_param.field2']['type']);

        $ruleset = [
            'array_of_objects_with_array.*.another.*.one.field1.*' => 'string|required',
            'array_of_objects_with_array.*.another.*.one.field2' => 'integer',
            'array_of_objects_with_array.*.another.*.two.field2' => 'numeric',
        ];
        $results = $this->strategy->parse($ruleset);
        $this->assertCount(7, $results);
        $this->assertEquals('object[]', $results['array_of_objects_with_array']['type']);
        $this->assertEquals('object[]', $results['array_of_objects_with_array[].another']['type']);
        $this->assertEquals('object', $results['array_of_objects_with_array[].another[].one']['type']);
        $this->assertEquals('object', $results['array_of_objects_with_array[].another[].two']['type']);
        $this->assertEquals('string[]', $results['array_of_objects_with_array[].another[].one.field1']['type']);
        $this->assertEquals('integer', $results['array_of_objects_with_array[].another[].one.field2']['type']);
        $this->assertEquals('number', $results['array_of_objects_with_array[].another[].two.field2']['type']);
    }

    public function supportedRules()
    {
        $description = 'A description';
        // Key is just an identifier
        // First array in each key is the validation ruleset,
        // Second is custom information (from bodyParameters() or comments)
        // Third is expected result

        yield 'string' => [
            ['string_param' => 'string'],
            ['string_param' => ['description' => $description]],
            [
                'type' => 'string',
                'description' => $description . ".",
            ],
        ];
        yield 'boolean' => [
            ['boolean_param' => 'boolean'],
            [],
            [
                'type' => 'boolean',
                'description' => "",
            ],
        ];
        yield 'integer' => [
            ['integer_param' => 'integer'],
            [],
            [
                'type' => 'integer',
                'description' => "",
            ],
        ];
        yield 'numeric' => [
            ['numeric_param' => 'numeric'],
            ['numeric_param' => ['description' => $description]],
            [
                'type' => 'number',
                'description' => $description . ".",
            ],
        ];
        yield 'array' => [
            ['array_param' => 'array'],
            [],
            [
                'type' => 'string[]',
                'description' => '',
            ],
        ];
        yield 'file' => [
            ['file_param' => 'file|required'],
            ['file_param' => ['description' => $description]],
            [
                'description' => "$description. The value must be a file.",
                'type' => 'file',
            ],
        ];
        yield 'timezone' => [
            ['timezone_param' => 'timezone|required'],
            [],
            [
                'description' => 'The value must be a valid time zone, such as <code>Africa/Accra</code>.',
                'type' => 'string',
            ],
        ];
        yield 'email' => [
            ['email_param' => 'email|required'],
            [],
            [
                'description' => 'The value must be a valid email address.',
                'type' => 'string',
            ],
        ];
        yield 'url' => [
            ['url_param' => 'url|required'],
            ['url_param' => ['description' => $description]],
            [
                'description' => "$description. The value must be a valid URL.",
                'type' => 'string',
            ],
        ];
        yield 'ip' => [
            ['ip_param' => 'ip|required'],
            ['ip_param' => ['description' => $description]],
            [
                'description' => "$description. The value must be a valid IP address.",
                'type' => 'string',
            ],
        ];
        yield 'json' => [
            ['json_param' => 'json|required'],
            ['json_param' => []],
            [
                'description' => 'The value must be a valid JSON string.',
                'type' => 'string',
            ],
        ];
        yield 'date' => [
            ['date_param' => 'date|required'],
            [],
            [
                'description' => 'The value must be a valid date.',
                'type' => 'string',
            ],
        ];
        yield 'date_format' => [
            ['date_format_param' => 'date_format:Y-m-d|required'],
            ['date_format_param' => ['description' => $description]],
            [
                'description' => "$description. The value must be a valid date in the format Y-m-d.",
                'type' => 'string',
            ],
        ];
        yield 'in' => [
            ['in_param' => 'in:3,5,6'],
            ['in_param' => ['description' => $description]],
            [
                'description' => "$description. The value must be one of <code>3</code>, <code>5</code>, or <code>6</code>.",
                'type' => 'string',
            ],
        ];
        yield 'digits' => [
            ['digits_param' => 'digits:8'],
            [],
            [
                'description' => "The value must be 8 digits.",
                'type' => 'number',
            ],
        ];
        yield 'digits_between' => [
            ['digits_between_param' => 'digits_between:2,8'],
            [],
            [
                'description' => "The value must be between 2 and 8 digits.",
                'type' => 'number',
            ],
        ];
        yield 'alpha' => [
            ['alpha_param' => 'alpha'],
            [],
            [
                'description' => "The value must contain only letters.",
                'type' => 'string',
            ],
        ];
        yield 'alpha_dash' => [
            ['alpha_dash_param' => 'alpha_dash'],
            [],
            [
                'description' => "The value must contain only letters, numbers, dashes and underscores.",
                'type' => 'string',
            ],
        ];
        yield 'alpha_num' => [
            ['alpha_num_param' => 'alpha_num'],
            [],
            [
                'description' => "The value must contain only letters and numbers.",
                'type' => 'string',
            ],
        ];
        yield 'ends_with' => [
            ['ends_with_param' => 'ends_with:go,ha'],
            [],
            [
                'description' => "The value must end with one of <code>go</code> or <code>ha</code>.",
                'type' => 'string',
            ],
        ];
        yield 'starts_with' => [
            ['starts_with_param' => 'starts_with:go,ha'],
            [],
            [
                'description' => "The value must start with one of <code>go</code> or <code>ha</code>.",
                'type' => 'string',
            ],
        ];
        yield 'uuid' => [
            ['uuid_param' => 'uuid'],
            [],
            [
                'description' => "The value must be a valid UUID.",
                'type' => 'string',
            ],
        ];
    }
}
