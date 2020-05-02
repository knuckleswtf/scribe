<?php

namespace Knuckles\Scribe\Tests\Extracting\Strategies\BodyParameters;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Extracting\BodyParam;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Orchestra\Testbench\TestCase;

class GetFromFormRequestTest extends TestCase
{
    use ArraySubsetAsserts;

    protected function getPackageProviders($app)
    {
        return [
            ScribeServiceProvider::class,
        ];
    }

    /** @test */
    public function can_fetch_from_form_request()
    {
        $methodName = 'withFormRequestParameter';
        $method = new \ReflectionMethod(TestController::class, $methodName);

        $strategy = new GetFromFormRequest(new DocumentationConfig([]));
        $results = $strategy->getBodyParametersFromFormRequest($method);

        $this->assertArraySubset([
            'user_id' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'The id of the user.',
                'value' => 9,
            ],
            'room_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'The id of the room.',
            ],
            'forever' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Whether to ban the user forever.',
                'value' => false,
            ],
            'another_one' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Just need something here.',
            ],
            'even_more_param' => [
                'type' => 'array',
                'required' => false,
                'description' => '',
            ],
            'book.name' => [
                'type' => 'string',
                'description' => '',
                'required' => false,
            ],
            'book.author_id' => [
                'type' => 'integer',
                'description' => '',
                'required' => false,
            ],
            'book[pages_count]' => [
                'type' => 'integer',
                'description' => '',
                'required' => false,
            ],
            'ids.*' => [
                'type' => 'integer',
                'description' => '',
                'required' => false,
            ],
            'users.*.first_name' => [
                'type' => 'string',
                'description' => 'The first name of the user.',
                'required' => false,
                'value' => 'John',
            ],
            'users.*.last_name' => [
                'type' => 'string',
                'description' => 'The last name of the user.',
                'required' => false,
                'value' => 'Doe',
            ],
        ], $results);

        $this->assertArrayNotHasKey('gets_ignored', $results);
    }
    /**
     * @test
     * @dataProvider supportedRules
     */
    public function can_handle_specific_rules($ruleset, $expected)
    {
        $strategy = new GetFromFormRequest(new DocumentationConfig([]));
        $results = $strategy->getBodyParametersFromValidationRules($ruleset);

        $parameterName = array_keys($ruleset)[0];

        if (isset($expected['required'])) {
            $this->assertEquals($expected['required'], $results[$parameterName]['required']);
        }

        if (!empty($expected['type'])) {
            $this->assertEquals($expected['type'], $results[$parameterName]['type']);
        }

        if (!empty($expected['description'])) {
            $this->assertStringEndsWith($expected['description'], $results[$parameterName]['description']);
        }

        // Validate that the generated values actually pass
        $validator = Validator::make([$parameterName => $results[$parameterName]['value']], $ruleset);
        try {
            $validator->validate();
        } catch (ValidationException $e) {
            dump('Value: ', $results[$parameterName]['value']);
            dump($e->errors());
            throw $e;
        }
    }

    public function supportedRules()
    {
        $description = 'A description';
        return [
            'required' => [
                ['required' => BodyParam::description($description)->rules('required')],
                [
                    'required' => true,
                ]
            ],
            'string' => [
                ['string' => BodyParam::description($description)->rules('string|required')],
                [
                    'type' => 'string',
                ]
            ],
            'boolean' => [
                ['boolean' => BodyParam::description($description)->rules('boolean|required')],
                [
                    'type' => 'boolean',
                ]
            ],
            'integer' => [
                ['integer' => BodyParam::description($description)->rules('integer|required')],
                [
                    'type' => 'integer',
                ]
            ],
            'numeric' => [
                ['numeric' => BodyParam::description($description)->rules('numeric|required')],
                [
                    'type' => 'number',
                ]
            ],
            'array' => [
                ['array' => BodyParam::description($description)->rules('array|required')],
                [
                    'type' => 'array',
                ]
            ],

            /* Ignore file fo now until we figure out how to support it
            'file' => [
                ['file' => BodyParam::description($description)->rules('file|required')],
                [
                    'type' => 'file',
                ]
            ],*/
            'timezone' => [
                ['timezone' => BodyParam::description($description)->rules('timezone|required')],
                [
                    'description' => 'The value must be a valid time zone, such as `Africa/Accra`.',
                    'type' => 'string',
                ]
            ],
            'email' => [
                ['email' => BodyParam::description($description)->rules('email|required')],
                [
                    'description' => 'The value must be a valid email address.',
                    'type' => 'string',
                ]
            ],
            'url' => [
                ['url' => BodyParam::description($description)->rules('url|required')],
                [
                    'description' => 'The value must be a valid URL.',
                    'type' => 'string',
                ]
            ],
            'ip' => [
                ['ip' => BodyParam::description($description)->rules('ip|required')],
                [
                    'description' => 'The value must be a valid IP address.',
                    'type' => 'string',
                ]
            ],
            'json' => [
                ['json' => BodyParam::description($description)->rules('json|required')],
                [
                    'description' => 'The value must be a valid JSON string.',
                    'type' => 'string',
                ]
            ],
            'date' => [
                ['date' => BodyParam::description($description)->rules('date|required')],
                [
                    'description' => 'The value must be a valid date.',
                    'type' => 'string',
                ]
            ],
            'date_format' => [
                ['date_format' => BodyParam::description($description)->rules('date_format:Y-m-d|required')],
                [
                    'description' => 'The value must be a valid date in the format Y-m-d.',
                    'type' => 'string',
                ]
            ],
            'in' => [
                ['in' => BodyParam::description($description)->rules('in:3,5,6|required')],
                [
                    'description' => 'The value must be one of `3`, `5`, or `6`.',
                    'type' => 'string',
                ]
            ],
        ];
    }

}
