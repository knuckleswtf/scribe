<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tests\Fixtures;

$invokableRulesSupported = interface_exists(\Illuminate\Contracts\Validation\InvokableRule::class);
$laravel10Rules = version_compare(Application::VERSION, '10.0', '>=');

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
            dump('Rules: ', $ruleset);
            dump('Generated value: ', $exampleData[$parameterName]);
            dump($e->errors());
            $this->fail("Generated example data from validation rule failed to match actual.");
        }
    }

    /** @test */
    public function can_parse_rule_objects()
    {
        $results = $this->strategy->parse([
            'in_param' => ['numeric', Rule::in([3,5,6])]
        ]);
        $this->assertEquals(
            [3, 5, 6],
            $results['in_param']['enumValues']
        );
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

    public static function supportedRules()
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
        yield 'file' => [
            ['file_param' => 'file|required'],
            ['file_param' => ['description' => $description]],
            [
                'description' => "$description. Must be a file.",
                'type' => 'file',
            ],
        ];
        yield 'image' => [
            ['image_param' => 'image|required'],
            [],
            [
                'description' => "Must be an image.",
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
                'description' => $description.".",
                'type' => 'string',
                'enumValues' => [3,5,6]
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
                'type' => 'string',
            ],
        ];
        yield 'digits_between' => [
            ['digits_between_param' => 'digits_between:2,8'],
            [],
            [
                'description' => "Must be between 2 and 8 digits.",
                'type' => 'string',
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
        yield 'different' => [
            ['different_param' => 'string|different:other_field'],
            [],
            ['description' => "The value and <code>other_field</code> must be different."],
        ];
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
        yield 'min (number)' => [
            ['min_param' => 'numeric|min:6'],
            [],
            ['description' => "Must be at least 6."],
        ];
        yield 'min (string)' => [
            ['min_param' => 'string|min:6'],
            [],
            ['description' => "Must be at least 6 characters."],
        ];
        yield 'min (file)' => [
            ['min_param' => 'file|min:6'],
            [],
            ['description' => "Must be a file. Must be at least 6 kilobytes."],
        ];
        yield 'between (number)' => [
            ['between_param' => 'numeric|between:1,2'],
            [],
            ['description' => "Must be between 1 and 2."],
        ];
        yield 'between (string)' => [
            ['between_param' => 'string|between:1,2'],
            [],
            ['description' => "Must be between 1 and 2 characters."],
        ];
        yield 'between (file)' => [
            ['between_param' => 'file|between:1,2'],
            [],
            ['description' => "Must be a file. Must be between 1 and 2 kilobytes."],
        ];
        yield 'regex' => [
            ['regex_param' => 'regex:/\d/'],
            [],
            ['description' => 'Must match the regex /\d/.'],
        ];
        yield 'accepted' => [
            ['accepted_param' => 'accepted'],
            [],
            [
                'type' => 'boolean',
                'description' => 'Must be accepted.',
            ],
        ];
        yield 'unsupported' => [
            ['unsupported_param' => [new DummyValidationRule, 'bail']],
            ['unsupported_param' => ['description' => $description]],
            ['description' => "$description."],
        ];
        if (version_compare(Application::VERSION, '8.53', '>=')) {
            yield 'accepted_if' => [
                ['accepted_if_param' => 'accepted_if:another_field,a_value'],
                [],
                [
                    'type' => 'boolean',
                    'description' => "Must be accepted when <code>another_field</code> is <code>a_value</code>.",
                ],
            ];
        }
    }

    /** @test */
    public function child_does_not_overwrite_parent_status()
    {
        $ruleset = [
            'array_param' => 'array|required',
            'array_param.*' => 'array|required',
            'array_param.*.an_item' => 'string|required',
        ];
        $results = $this->strategy->parse($ruleset);
        $this->assertCount(2, $results);
        $this->assertEquals(true, $results['array_param']['required']);
    }

    /** @test */
    public function can_parse_custom_closure_rules()
    {
        // Single line DocComment
        $ruleset = [
            'closure' => [
                'bail', 'required',
                /** This is a single line parsed closure rule. */
                function ($attribute, $value, $fail) {
                    $fail('Always fail.');
                },
            ],
        ];

        $results = $this->strategy->parse($ruleset);
        $this->assertEquals(
            'This is a single line parsed closure rule.',
            $results['closure']['description']
        );

        // Block DocComment
        $ruleset = [
            'closure' => [
                'bail', 'required',
                /**
                 * This is a block DocComment
                 * parsed on a closure rule.
                 * Extra info.
                 */
                function ($attribute, $value, $fail) {
                    $fail('Always fail.');
                },
            ],
        ];

        $results = $this->strategy->parse($ruleset);
        $this->assertEquals(
            'This is a block DocComment parsed on a closure rule. Extra info.',
            $results['closure']['description']
        );
    }

    /** @test */
    public function can_parse_custom_rule_classes()
    {
        $ruleset = [
            'param1' => ['bail', 'required', new DummyWithDocsValidationRule],
        ];

        global $invokableRulesSupported;
        if ($invokableRulesSupported) {
            $ruleset['param2'] = [new DummyInvokableValidationRule];
        }
        global $laravel10Rules;
        if ($laravel10Rules) {
            $ruleset['param3'] = [new DummyL10ValidationRule];
        }

        $results = $this->strategy->parse($ruleset);
        $this->assertEquals(true, $results['param1']['required']);
        $this->assertEquals('This is a dummy test rule.', $results['param1']['description']);
        if (isset($results['param2'])) $this->assertEquals('This rule is invokable.', $results['param2']['description']);
       if (isset($results['param3'])) $this->assertEquals('This is a custom rule.', $results['param3']['description']);
    }

    /** @test */
    public function can_parse_enum_rules()
    {
        if (phpversion() < 8.1) {
            $this->markTestSkipped('Enums are only supported in PHP 8.1 or later');
        }

        $results = $this->strategy->parse([
            'enum' => ['required', Rule::enum(Fixtures\TestStringBackedEnum::class)],
        ]);
        $this->assertEquals('string', $results['enum']['type']);
        $this->assertEquals(
            ['red', 'green', 'blue'],
            $results['enum']['enumValues']
        );
        $this->assertTrue(in_array(
            $results['enum']['example'],
            array_map(fn ($case) => $case->value, Fixtures\TestStringBackedEnum::cases())
        ));


        $results = $this->strategy->parse([
            'enum' => ['required', Rule::enum(Fixtures\TestIntegerBackedEnum::class)],
        ]);
        $this->assertEquals('integer', $results['enum']['type']);
        $this->assertEquals(
            [1, 2, 3],
            $results['enum']['enumValues']
        );
        $this->assertTrue(in_array(
            $results['enum']['example'],
            array_map(fn ($case) => $case->value, Fixtures\TestIntegerBackedEnum::cases())
        ));

        $results = $this->strategy->parse([
            'enum' => ['required', Rule::enum(Fixtures\TestStringBackedEnum::class)],
        ], [
            'enum' => ['description' => 'A description'],
        ]);
        $this->assertEquals('string', $results['enum']['type']);
        $this->assertEquals(
            'A description.',
            $results['enum']['description']
        );
        $this->assertTrue(in_array(
            $results['enum']['example'],
            array_map(fn ($case) => $case->value, Fixtures\TestStringBackedEnum::cases())
        ));
    }
}

class DummyValidationRule implements \Illuminate\Contracts\Validation\Rule
{
    public function passes($attribute, $value)
    {
        return true;
    }

    public function message()
    {
        return '.';
    }
}

class DummyWithDocsValidationRule implements \Illuminate\Contracts\Validation\Rule
{
    public function passes($attribute, $value)
    {
        return true;
    }

    public function message()
    {
        return '.';
    }

    public static function docs()
    {
        return [
            'description' => 'This is a dummy test rule.',
            'example' => 'Default example, only added if none other give.',
        ];
    }
}

if ($invokableRulesSupported) {
    class DummyInvokableValidationRule implements \Illuminate\Contracts\Validation\InvokableRule
    {
        public function __invoke($attribute, $value, $fail)
        {
            if (strtoupper($value) !== $value) {
                $fail(':attribute must be uppercase.');
            }
        }

        public function docs()
        {

            return [
                'description' => 'This rule is invokable.',
            ];
        }
    }
}

if ($laravel10Rules) {
// Laravel 10 deprecated the previous Rule and InvokableRule classes for a single interface
    // (https://github.com/laravel/framework/pull/45954)
    class DummyL10ValidationRule implements \Illuminate\Contracts\Validation\ValidationRule
    {
        public function validate(string $attribute, mixed $value, \Closure $fail): void
        {
            if (strtoupper($value) !== $value) {
                $fail('The :attribute must be an attribute.');
            }
        }

        public static function docs()
        {
            return [
                'description' => 'This is a custom rule.',
            ];
        }
    }
}
