<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Foundation\Application;
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
                $bodyParametersFromValidationRules = $this->getParametersFromValidationRules($validationRules, $customParameterData);
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

        $this->assertEquals($expected['description'], $results[$parameterName]['description']);
        if (isset($expected['type'])) {
            $this->assertEquals($expected['type'], $results[$parameterName]['type']);
        }

        // Validate that the generated values actually pass validation
        $exampleData = [$parameterName => $results[$parameterName]['example']];
        $validator = Validator::make($exampleData, $ruleset);
        try {
            $validator->validate();
        } catch (ValidationException $e) {
            dump('Value: ', $exampleData[$parameterName]);
            dump($e->errors());
            $this->fail("Generated example data from validation rule failed to match actual.");
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
                'description' => "$description. Must be a file.",
                'type' => 'file',
            ],
        ];
        yield 'timezone' => [
            ['timezone_param' => 'timezone|required'],
            [],
            [
                'description' => 'Must be a valid time zone, such as <code>Africa/Accra</code>.',
                'type' => 'string',
            ],
        ];
        yield 'email' => [
            ['email_param' => 'email|required'],
            [],
            [
                'description' => 'Must be a valid email address.',
                'type' => 'string',
            ],
        ];
        yield 'url' => [
            ['url_param' => 'url|required'],
            ['url_param' => ['description' => $description]],
            [
                'description' => "$description. Must be a valid URL.",
                'type' => 'string',
            ],
        ];
        yield 'ip' => [
            ['ip_param' => 'ip|required'],
            ['ip_param' => ['description' => $description]],
            [
                'description' => "$description. Must be a valid IP address.",
                'type' => 'string',
            ],
        ];
        yield 'json' => [
            ['json_param' => 'json|required'],
            ['json_param' => []],
            [
                'description' => 'Must be a valid JSON string.',
                'type' => 'string',
            ],
        ];
        yield 'date' => [
            ['date_param' => 'date|required'],
            [],
            [
                'description' => 'Must be a valid date.',
                'type' => 'string',
            ],
        ];
        yield 'date_format' => [
            ['date_format_param' => 'date_format:Y-m-d|required'],
            ['date_format_param' => ['description' => $description]],
            [
                'description' => "$description. Must be a valid date in the format <code>Y-m-d</code>.",
                'type' => 'string',
            ],
        ];
        yield 'in' => [
            ['in_param' => 'in:3,5,6'],
            ['in_param' => ['description' => $description]],
            [
                'description' => "$description. Must be one of <code>3</code>, <code>5</code>, or <code>6</code>.",
                'type' => 'string',
            ],
        ];
        yield 'not_in' => [
            ['not_param' => 'not_in:3,5,6'],
            [],
            [
                'description' => "Must not be one of <code>3</code>, <code>5</code>, or <code>6</code>.",
            ],
        ];
        yield 'digits' => [
            ['digits_param' => 'digits:8'],
            [],
            [
                'description' => "Must be 8 digits.",
                'type' => 'number',
            ],
        ];
        yield 'digits_between' => [
            ['digits_between_param' => 'digits_between:2,8'],
            [],
            [
                'description' => "Must be between 2 and 8 digits.",
                'type' => 'number',
            ],
        ];
        yield 'alpha' => [
            ['alpha_param' => 'alpha'],
            [],
            [
                'description' => "Must contain only letters.",
                'type' => 'string',
            ],
        ];
        yield 'alpha_dash' => [
            ['alpha_dash_param' => 'alpha_dash'],
            [],
            [
                'description' => "Must contain only letters, numbers, dashes and underscores.",
                'type' => 'string',
            ],
        ];
        yield 'alpha_num' => [
            ['alpha_num_param' => 'alpha_num'],
            [],
            [
                'description' => "Must contain only letters and numbers.",
                'type' => 'string',
            ],
        ];
        yield 'ends_with' => [
            ['ends_with_param' => 'ends_with:go,ha'],
            [],
            [
                'description' => "Must end with one of <code>go</code> or <code>ha</code>.",
                'type' => 'string',
            ],
        ];
        yield 'starts_with' => [
            ['starts_with_param' => 'starts_with:go,ha'],
            [],
            [
                'description' => "Must start with one of <code>go</code> or <code>ha</code>.",
                'type' => 'string',
            ],
        ];
        yield 'uuid' => [
            ['uuid_param' => 'uuid'],
            [],
            [
                'description' => "Must be a valid UUID.",
                'type' => 'string',
            ],
        ];
        yield 'required_if' => [
            ['required_if_param' => 'required_if:another_field,a_value'],
            [],
            ['description' => "This field is required when <code>another_field</code> is <code>a_value</code>."],
        ];
        yield 'required_unless' => [
            ['required_unless_param' => 'string|required_unless:another_field,a_value'],
            [],
            ['description' => "This field is required unless <code>another_field</code> is in <code>a_value</code>."],
        ];
        yield 'required_with' => [
            ['required_with_param' => 'required_with:another_field,some_other_field'],
            [],
            ['description' => 'This field is required when <code>another_field</code> or <code>some_other_field</code> is present.'],
        ];
        yield 'required_with_all' => [
            ['required_with_all_param' => 'required_with_all:another_field,some_other_field'],
            [],
            ['description' => 'This field is required when <code>another_field</code> and <code>some_other_field</code> are present.'],
        ];
        yield 'required_without' => [
            ['required_without_param' => 'string|required_without:another_field,some_other_field'],
            [],
            ['description' => 'This field is required when <code>another_field</code> or <code>some_other_field</code> is not present.'],
        ];
        yield 'required_without_all' => [
            ['required_without_all_param' => 'string|required_without_all:another_field,some_other_field'],
            [],
            ['description' => 'This field is required when none of <code>another_field</code> and <code>some_other_field</code> are present.'],
        ];
        yield 'same' => [
            ['same_param' => 'same:other_field'],
            [],
            ['description' => "The value and <code>other_field</code> must match."],
        ];
        if (version_compare(Application::VERSION, '7.0.0', '<')) {
            yield 'different' => [
                ['different_param' => 'string|different:other_field'],
                [],
                ['description' => "The value and <code>other_field</code> must be different."],
            ];
        }
        yield 'after' => [
            ['after_param' => 'after:2020-02-12'],
            [],
            ['description' => "Must be a date after <code>2020-02-12</code>."],
        ];
        yield 'before_or_equal' => [
            ['before_or_equal_param' => 'before_or_equal:2020-02-12'],
            [],
            ['description' => "Must be a date before or equal to <code>2020-02-12</code>."],
        ];
        yield 'size (number)' => [
            ['size_param' => 'numeric|size:6'],
            [],
            ['description' => "Must be 6."],
        ];
        yield 'size (string)' => [
            ['size_param' => 'string|size:6'],
            [],
            ['description' => "Must be 6 characters."],
        ];
        yield 'size (file)' => [
            ['size_param' => 'file|size:6'],
            [],
            ['description' => "Must be a file. Must be 6 kilobytes."],
        ];
        yield 'max (number)' => [
            ['max_param' => 'numeric|max:6'],
            [],
            ['description' => "Must not be greater than 6."],
        ];
        yield 'max (string)' => [
            ['max_param' => 'string|max:6'],
            [],
            ['description' => "Must not be greater than 6 characters."],
        ];
        yield 'max (file)' => [
            ['max_param' => 'file|max:6'],
            [],
            ['description' => "Must be a file. Must not be greater than 6 kilobytes."],
        ];
    }
}
