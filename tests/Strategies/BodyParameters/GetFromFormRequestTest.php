<?php

namespace Knuckles\Scribe\Tests\Strategies\BodyParameters;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class GetFromFormRequestTest extends BaseLaravelTest
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_form_request()
    {
        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameter');

        $strategy = new GetFromFormRequest(new DocumentationConfig([]));
        $results = $strategy->getBodyParametersFromFormRequest($method);

        $this->assertArraySubset([
            'user_id' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'The id of the user.',
                'example' => 9,
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
                'example' => false,
            ],
            'another_one' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Just need something here.',
            ],
            'even_more_param' => [
                'type' => 'string[]',
                'required' => false,
                'description' => '',
            ],
            'book' => [
                'type' => 'object',
                'description' => '',
                'required' => false,
                'example' => [],
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
            'book.pages_count' => [
                'type' => 'integer',
                'description' => '',
                'required' => false,
            ],
            'ids' => [
                'type' => 'integer[]',
                'description' => '',
                'required' => false,
            ],
            'users' => [
                'type' => 'object[]',
                'description' => '',
                'required' => false,
                'example' => [[]],
            ],
            'users[].first_name' => [
                'type' => 'string',
                'description' => 'The first name of the user.',
                'required' => false,
                'example' => 'John',
            ],
            'users[].last_name' => [
                'type' => 'string',
                'description' => 'The last name of the user.',
                'required' => false,
                'example' => 'Doe',
            ],
        ], $results);

        $this->assertIsArray($results['ids']['example']);
    }
}
