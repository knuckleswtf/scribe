<?php

namespace Knuckles\Scribe\Tests\Strategies;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tests\Fixtures\TestRequest;
use Knuckles\Scribe\Tests\Fixtures\TestRequestQueryParams;
use Knuckles\Scribe\Tools\DocumentationConfig;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Knuckles\Scribe\Tools\Globals;
use PHPUnit\Framework\Assert;

class GetFromFormRequestTest extends BaseLaravelTest
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_bodyparams_from_form_request()
    {
        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameter');
        $results = $this->fetchViaBodyParams($method);

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
            'no_example_attribute' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Attribute without example.',
                'example' => null,
            ],
            'even_more_param' => [
                'type' => 'object',
                'required' => false,
                'description' => '',
                'example' => [],
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
        $this->assertIsInt($results['ids']['example'][0]);
    }

    /** @test */
    public function can_fetch_queryparams_from_form_request()
    {
        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameterQueryParams');
        $results = $this->fetchViaQueryParams($method);

        $this->assertArraySubset([
            'q_param' => [
                'type' => 'integer',
                'description' => 'The param.',
                'required' => true,
                'example' => 9,
            ],
        ], $results);

        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameterQueryParamsComment');
        $results = $this->fetchViaQueryParams($method);

        $this->assertArraySubset([
            'type' => 'integer',
            'description' => '',
            'required' => true,
        ], $results['q_param']);
    }

    /** @test */
    public function will_ignore_not_relevant_form_request()
    {
        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameter');
        $this->assertEquals([], $this->fetchViaQueryParams($method));

        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameterQueryParams');
        $this->assertEquals([], $this->fetchViaBodyParams($method));

        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameterQueryParamsComment');
        $this->assertEquals([], $this->fetchViaBodyParams($method));
    }

    /** @test */
    public function sets_examples_from_parent_if_set()
    {
        $strategy = new BodyParameters\GetFromFormRequest(new DocumentationConfig([]));
        $dataExample = [
            'title' => 'Things Fall Apart',
            'meta' => ['tags' => ['epic']],
        ];
        $parametersFromFormRequest = $strategy->getParametersFromValidationRules(
            [
                'data' => 'array|required',
                'data.title' => 'string|required',
                'data.meta' => 'array',
                'data.meta.tags' => 'array',
                'data.meta.tags.*' => 'string',
            ],
            [
                'data' => [
                    'example' => $dataExample,
                ],
            ],
        );

        $parsed = $strategy->normaliseArrayAndObjectParameters($parametersFromFormRequest);
        $this->assertEquals($dataExample, $parsed['data']['example']);
        $this->assertEquals($dataExample['title'], $parsed['data.title']['example']);
        $this->assertEquals($dataExample['meta'], $parsed['data.meta']['example']);
        $this->assertEquals($dataExample['meta']['tags'], $parsed['data.meta.tags']['example']);
    }

    /** @test */
    public function generates_proper_examples_if_not_set()
    {
        $strategy = new BodyParameters\GetFromFormRequest(new DocumentationConfig([]));
        $parametersFromFormRequest = $strategy->getParametersFromValidationRules(
            [
                'data' => 'array|required',
                'data.title' => 'string|required',
                'data.meta' => 'array',
                'data.meta.tags' => 'array',
                'data.meta.tags.*' => 'string',
            ],
            []
        );

        $parsed = $strategy->normaliseArrayAndObjectParameters($parametersFromFormRequest);
        $this->assertEquals([], $parsed['data']['example']);
        $this->assertTrue(is_string($parsed['data.title']['example']));
        $this->assertNull($parsed['data.meta']['example']); // null because not required
        $this->assertTrue(is_array($parsed['data.meta.tags']['example']));
        $this->assertTrue(is_string($parsed['data.meta.tags']['example'][0]));
    }

    /** @test */
    public function creates_missing_parent_fields()
    {
        $strategy = new BodyParameters\GetFromFormRequest(new DocumentationConfig([]));
        $parametersFromFormRequest = $strategy->getParametersFromValidationRules(
            [
                'cars.*.dogs.*.*' => 'array',
                'thing1.thing2.*.thing3.thing4' => 'int',
            ],
            [],
        );

        $expected = [
            'cars' => ['type' => 'object[]'],
            'cars[].dogs' => ['type' => 'object[][]'],
            'thing1' => ['type' => 'object'],
            'thing1.thing2' => ['type' => 'object[]'],
            'thing1.thing2[].thing3' => ['type' => 'object'],
            'thing1.thing2[].thing3.thing4' => ['type' => 'integer'],
        ];
        $parsed = $strategy->normaliseArrayAndObjectParameters($parametersFromFormRequest);
        $this->assertArraySubset($expected, $parsed);
    }

    /** @test */
    public function allows_customisation_of_form_request_instantiation()
    {
        $controllerMethod = new \ReflectionMethod(TestController::class, 'withFormRequestParameter');

        Globals::$__instantiateFormRequestUsing = function (string $className, Route $route, string $method) use (&$controllerMethod) {
            Assert::assertEquals(TestRequest::class, $className);
            Assert::assertEquals($controllerMethod, $method);
            return new TestRequestQueryParams;
        };

        $this->fetchViaBodyParams($controllerMethod);

        Globals::$__instantiateFormRequestUsing = null;
    }

    /** @test */
    public function custom_rule_example_doesnt_override_form_request_example()
    {
        $strategy = new BodyParameters\GetFromFormRequest(new DocumentationConfig([]));
        $parametersFromFormRequest = $strategy->getParametersFromValidationRules(
            [
                'dummy' => ['required', new DummyValidationRule],
            ],
            [
                'dummy' => [
                    'description' => 'New description.',
                    'example' => 'Overrided example.',
                ],
            ],
        );

        $parsed = $strategy->normaliseArrayAndObjectParameters($parametersFromFormRequest);
        $this->assertEquals('Overrided example.', $parsed['dummy']['example']);
        $this->assertEquals('New description. This is a dummy test rule.', $parsed['dummy']['description']);
    }

    protected function fetchViaBodyParams(\ReflectionMethod $method): array
    {
        $strategy = new BodyParameters\GetFromFormRequest(new DocumentationConfig([]));
        $route = new Route(['POST'], "/test", ['uses' => [TestController::class, 'dummy']]);
        return $strategy->getParametersFromFormRequest($method, $route);
    }

    protected function fetchViaQueryParams(\ReflectionMethod $method): array
    {
        $strategy = new QueryParameters\GetFromFormRequest(new DocumentationConfig([]));
        $route = new Route(['POST'], "/test", ['uses' => [TestController::class, 'dummy']]);
        return $strategy->getParametersFromFormRequest($method, $route);
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

    public static function docs()
    {
        return [
            'description' => 'This is a dummy test rule.',
            'example' => 'Default example, only added if none other give.',
        ];
    }
}
