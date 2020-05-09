<?php

namespace Knuckles\Scribe\Tests\Strategies\BodyParameters;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
        $providers = [
            ScribeServiceProvider::class,
        ];
        if (class_exists(\Dingo\Api\Provider\LaravelServiceProvider::class)) {
            $providers[] = \Dingo\Api\Provider\LaravelServiceProvider::class;
        }
        return $providers;
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
    }

    /**
     * @test
     * @dataProvider supportedRules
     */
    public function can_handle_specific_rules($ruleset, $customInfo, $expected)
    {
        $strategy = new GetFromFormRequest(new DocumentationConfig([]));
        $results = $strategy->getBodyParametersFromValidationRules($ruleset, $customInfo);

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
                ['required_param' => 'required'],
                ['required_param' => ['description' => $description]],
                [
                    'required' => true,
                ],
            ],
            'string' => [
                ['string_param' => 'string|required'],
                ['string_param' => ['description' => $description]],
                [
                    'type' => 'string',
                ],
            ],
            'boolean' => [
                ['boolean_param' => 'boolean|required'],
                ['boolean_param' => ['description' => $description]],
                [
                    'type' => 'boolean',
                ],
            ],
            'integer' => [
                ['integer_param' => 'integer|required'],
                ['integer_param' => ['description' => $description]],
                [
                    'type' => 'integer',
                ],
            ],
            'numeric' => [
                ['numeric_param' => 'numeric|required'],
                ['numeric_param' => ['description' => $description]],
                [
                    'type' => 'number',
                ],
            ],
            'array' => [
                ['array_param' => 'array|required'],
                ['array_param' => ['description' => $description]],
                [
                    'type' => 'array',
                ],
            ],
            'file' => [
                ['file_param' => 'file|required'],
                ['file_param' => ['description' => $description]],
                [
                    'description' => 'The value must be a file.',
                    'type' => 'file',
                ],
            ],
            'timezone' => [
                ['timezone_param' => 'timezone|required'],
                ['timezone_param' => ['description' => $description]],
                [
                    'description' => 'The value must be a valid time zone, such as <code>Africa/Accra</code>.',
                    'type' => 'string',
                ],
            ],
            'email' => [
                ['email_param' => 'email|required'],
                ['email_param' => ['description' => $description]],
                [
                    'description' => 'The value must be a valid email address.',
                    'type' => 'string',
                ],
            ],
            'url' => [
                ['url_param' => 'url|required'],
                ['url_param' => ['description' => $description]],
                [
                    'description' => 'The value must be a valid URL.',
                    'type' => 'string',
                ],
            ],
            'ip' => [
                ['ip_param' => 'ip|required'],
                ['ip_param' => ['description' => $description]],
                [
                    'description' => 'The value must be a valid IP address.',
                    'type' => 'string',
                ],
            ],
            'json' => [
                ['json_param' => 'json|required'],
                ['json_param' => ['description' => $description]],
                [
                    'description' => 'The value must be a valid JSON string.',
                    'type' => 'string',
                ],
            ],
            'date' => [
                ['date_param' => 'date|required'],
                ['date_param' => ['description' => $description]],
                [
                    'description' => 'The value must be a valid date.',
                    'type' => 'string',
                ],
            ],
            'date_format' => [
                ['date_format_param' => 'date_format:Y-m-d|required'],
                ['date_format_param' => ['description' => $description]],
                [
                    'description' => 'The value must be a valid date in the format Y-m-d.',
                    'type' => 'string',
                ],
            ],
            'in' => [
                ['in_param' => 'in:3,5,6|required'],
                ['in_param' => ['description' => $description]],
                [
                    'description' => 'The value must be one of <code>3</code>, <code>5</code>, or <code>6</code>.',
                    'type' => 'string',
                ],
            ],
        ];
    }

}
