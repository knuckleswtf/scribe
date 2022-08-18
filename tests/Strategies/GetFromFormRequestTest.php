<?php

namespace Knuckles\Scribe\Tests\Strategies;

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

        $strategy = new BodyParameters\GetFromFormRequest(new DocumentationConfig([]));
        $results = $strategy->getParametersFromFormRequest($method);

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

    /** @test */
    public function can_fetch_queryparams_from_form_request()
    {
        $strategy = new QueryParameters\GetFromFormRequest(new DocumentationConfig([]));

        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameterQueryParams');
        $results = $strategy->getParametersFromFormRequest($method);

        $this->assertArraySubset([
            'q_param' => [
                'type' => 'integer',
                'description' => 'The param.',
                'required' => true,
                'example' => 9,
            ],
        ], $results);

        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameterQueryParamsComment');
        $results = $strategy->getParametersFromFormRequest($method);

        $this->assertArraySubset([
            'type' => 'integer',
            'description' => '',
            'required' => true,
        ], $results['q_param']);
    }

    /** @test */
    public function will_ignore_not_relevant_form_request()
    {
        $queryParamsStrategy = new QueryParameters\GetFromFormRequest(new DocumentationConfig([]));
        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameter');
        $results = $queryParamsStrategy->getParametersFromFormRequest($method);
        $this->assertEquals([], $results);

        $bodyParamsStrategy = new BodyParameters\GetFromFormRequest(new DocumentationConfig([]));
        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameterQueryParams');
        $results = $bodyParamsStrategy->getParametersFromFormRequest($method);
        $this->assertEquals([], $results);

        $method = new \ReflectionMethod(TestController::class, 'withFormRequestParameterQueryParamsComment');
        $results = $bodyParamsStrategy->getParametersFromFormRequest($method);
        $this->assertEquals([], $results);
    }

    /** @test */
    public function allows_customisation_of_form_request_instantiation()
    {
        $controllerMethod = new \ReflectionMethod(TestController::class, 'withFormRequestParameter');

        Globals::$__instantiateFormRequestUsing = function ($className, $route, $method) use (&$controllerMethod) {
            Assert::assertEquals(TestRequest::class, $className);
            Assert::assertEquals(null, $route);
            Assert::assertEquals($controllerMethod, $method);
            return new TestRequestQueryParams;
        };

        $strategy = new BodyParameters\GetFromFormRequest(new DocumentationConfig([]));
        $strategy->getParametersFromFormRequest($controllerMethod);

        Globals::$__instantiateFormRequestUsing = null;
    }
}
