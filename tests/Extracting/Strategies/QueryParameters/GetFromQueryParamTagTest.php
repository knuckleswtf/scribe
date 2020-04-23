<?php

namespace Knuckles\Scribe\Tests\Extracting\Strategies\QueryParameters;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

class GetFromQueryParamTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_queryparam_tag()
    {
        $strategy = new GetFromQueryParamTag(new DocumentationConfig([]));
        $tags = [
            new Tag('queryParam', 'location_id required The id of the location.'),
            new Tag('queryParam', 'user_id required The id of the user. Example: me'),
            new Tag('queryParam', 'page required The page number. Example: 4'),
            new Tag('queryParam', 'filters.* The filters.'),
            new Tag('queryParam', 'url_encoded Used for testing that URL parameters will be URL-encoded where needed. Example: + []&='),
        ];
        $results = $strategy->getQueryParametersFromDocBlock($tags);

        $this->assertArraySubset([
            'location_id' => [
                'required' => true,
                'description' => 'The id of the location.',
            ],
            'user_id' => [
                'required' => true,
                'description' => 'The id of the user.',
                'value' => 'me',
            ],
            'page' => [
                'required' => true,
                'description' => 'The page number.',
                'value' => '4',
            ],
            'filters.*' => [
                'required' => false,
                'description' => 'The filters.',
            ],
        ], $results);
    }

    /** @test */
    public function can_fetch_from_form_request_method_argument()
    {
        $methodName = 'withFormRequestParameter';
        $method = new \ReflectionMethod(TestController::class, $methodName);
        $route = new Route(['POST'], "/$methodName", ['uses' => TestController::class . "@$methodName"]);

        $strategy = new GetFromQueryParamTag(new DocumentationConfig([]));
        $results = $strategy->getQueryParametersFromFormRequestOrMethod($route, $method);

        $this->assertArraySubset([
            'location_id' => [
                'required' => true,
                'description' => 'The id of the location.',
            ],
            'user_id' => [
                'required' => true,
                'description' => 'The id of the user.',
                'value' => 'me',
            ],
            'page' => [
                'required' => true,
                'description' => 'The page number.',
                'value' => '4',
            ],
            'filters.*' => [
                'required' => false,
                'description' => 'The filters.',
            ],
        ], $results);
    }


}
