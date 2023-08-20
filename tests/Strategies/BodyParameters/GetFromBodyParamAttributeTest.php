<?php

namespace Knuckles\Scribe\Tests\Strategies\BodyParameters;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamAttribute;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ReflectionClass;
use ReflectionFunction;

class GetFromBodyParamAttributeTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_bodyparam_attribute()
    {
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(BodyParamAttributeTestController::class);
            $e->method = $e->controller->getMethod('methodWithAttributes');
        });
        $results = $this->fetch($endpoint);

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
            'yet_another_param' => [
                'type' => 'object',
                'required' => true,
                'description' => 'Some object params.',
            ],
            'yet_another_param.name' => [
                'type' => 'string',
                'description' => '',
                'required' => true,
            ],
            'even_more_param' => [
                'type' => 'number[]',
                'description' => 'A list of numbers',
                'required' => false,
            ],
            'book' => [
                'type' => 'object',
                'description' => 'Book information',
                'required' => false,
            ],
            'book.name' => [
                'type' => 'string',
                'description' => '',
                'required' => true,
            ],
            'book.author_id' => [
                'type' => 'integer',
                'description' => '',
                'required' => true,
            ],
            'book.pages_count' => [
                'type' => 'integer',
                'description' => '',
                'required' => true,
            ],
            'ids' => [
                'type' => 'integer[]',
                'description' => '',
                'required' => true,
            ],
            'state' => [
                'type' => 'string',
                'description' => '',
                'required' => true,
                'enumValues' => ["active", "pending"]
            ],
            'users' => [
                'type' => 'object[]',
                'description' => 'Users\' details',
                'required' => false,
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
    }


    /** @test */
    public function can_fetch_from_bodyparam_attribute_on_formrequest()
    {
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(BodyParamAttributeTestController::class);
            $e->method = $e->controller->getMethod('methodWithFormRequest');
        });
        $results = $this->fetch($endpoint);

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
            'param' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'A parameter.',
                'example' => 19,
            ],
        ], $results);
    }

    /** @test */
    public function can_fetch_from_bodyparam_attribute_for_array_body()
    {
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = null;
            $e->method = new ReflectionFunction('\Knuckles\Scribe\Tests\Strategies\BodyParameters\functionWithAttributes');
        });
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            '[].first_name' => [
                'type' => 'string',
                'description' => 'The first name of the user.',
                'required' => true,
                'example' => 'John',
            ],
            '[].last_name' => [
                'type' => 'string',
                'description' => 'The last name of the user.',
                'required' => true,
                'example' => 'Doe',
            ],
            '[].contacts[].first_name' => [
                'type' => 'string',
                'description' => 'The first name of the contact.',
                'required' => false,
                'example' => 'John',
            ],
            '[].contacts[].last_name' => [
                'type' => 'string',
                'description' => 'The last name of the contact.',
                'required' => false,
                'example' => 'Doe',
            ],
            '[].roles' => [
                'type' => 'string[]',
                'description' => 'The name of the role.',
                'required' => true,
                'example' => ['Admin'],
            ],
        ], $results);
    }

    protected function fetch($endpoint): array
    {
        $strategy = new GetFromBodyParamAttribute(new DocumentationConfig([]));
        return $strategy($endpoint, []);
    }

    protected function endpoint(Closure $configure): ExtractedEndpointData
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = []) {}
        };
        $configure($endpoint);
        return $endpoint;
    }

}


#[BodyParam("user_id", description: "Will be overriden.")]
#[BodyParam("room_id", "string", "The id of the room.", example: "4", required: false)]
class BodyParamAttributeTestController
{
    #[BodyParam("user_id", description: "The id of the user.", example: 9, type: "int")]
    #[BodyParam("forever", "boolean", "Whether to ban the user forever.", example: false, required: false)]
    #[BodyParam("another_one", "number", "Just need something here.", required: false)]
    #[BodyParam("yet_another_param", "object", description: "Some object params.")]
    #[BodyParam("yet_another_param.name", "string")]
    #[BodyParam("even_more_param", "number[]", "A list of numbers", required: false)]
    #[BodyParam("book", "object", "Book information", required: false)]
    #[BodyParam("book.name", type: "string")]
    #[BodyParam("book.author_id", type: "integer")]
    #[BodyParam("book.pages_count", type: "integer")]
    #[BodyParam("ids", "integer[]")]
    #[BodyParam("state", enum: ["active", "pending"])]
    #[BodyParam("users", "object[]", "Users' details", required: false)]
    #[BodyParam("users[].first_name", "string", "The first name of the user.", example: "John", required: false)]
    #[BodyParam("users[].last_name", "string", "The last name of the user.", example: "Doe", required: false)]
    public function methodWithAttributes()
    {

    }

    public function methodWithFormRequest(BodyParamAttributeTestFormRequest $request)
    {

    }
}

#[BodyParam("user_id", description: "The id of the user.", example: 9, type: "int")]
#[BodyParam('param', 'integer', 'A parameter.', example: 19)]
class BodyParamAttributeTestFormRequest extends FormRequest
{
    public function rules()
    {
        return [];
    }
}

#[BodyParam('[].first_name', 'string', 'The first name of the user.', example: 'John')]
#[BodyParam('[].last_name', 'string', 'The last name of the user.', example: 'Doe')]
#[BodyParam('[].contacts[].first_name', 'string', 'The first name of the contact.', example: 'John', required: false)]
#[BodyParam('[].contacts[].last_name', 'string', 'The last name of the contact.', example: 'Doe', required: false)]
#[BodyParam('[].roles', 'string[]', 'The name of the role.', example: ["Admin"])]
function functionWithAttributes() {

}
