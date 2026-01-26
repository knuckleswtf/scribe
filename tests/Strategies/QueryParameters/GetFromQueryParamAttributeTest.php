<?php

namespace Knuckles\Scribe\Tests\Strategies\QueryParameters;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamAttribute;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class GetFromQueryParamAttributeTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function canFetchFromQueryparamAttribute()
    {
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new \ReflectionClass(QueryParamAttributeTestController::class);
            $e->method = $e->controller->getMethod('methodWithAttributes');
        });
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            'location_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'The id of the location.',
            ],
            'user_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'The id of the user.',
                'example' => 'me',
            ],
            'page' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'The page number.',
                'example' => 4,
            ],
            'with_type' => [
                'type' => 'number',
                'required' => false,
                'description' => '',
                'example' => 13.0,
            ],
            'with_list_type' => [
                'type' => 'integer[]',
                'required' => false,
                'description' => '',
            ],
            'fields' => [
                'type' => 'string[]',
                'required' => false,
                'description' => 'The fields.',
                'example' => ['age', 'name'],
            ],
            'filters' => [
                'type' => 'object',
                'required' => false,
                'description' => 'The filters.',
            ],
            'filters.class' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Class.',
                'example' => 11.0,
            ],
            'filters.other' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Other things.',
            ],
            'noExampleNoDescription' => [
                'type' => 'string',
                'required' => true,
                'description' => '',
                'example' => null,
            ],
            'noExample' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Something',
                'example' => null,
            ],
            'book_id' => [
                'type' => 'string',
                'description' => 'Book ID',
                'required' => true,
                'deprecated' => true,
            ],
            'team_id' => [
                'type' => 'string',
                'description' => 'Team ID',
                'required' => true,
                'deprecated' => true,
            ],
            'device' => [
                'type' => 'object',
                'description' => 'Device',
                'required' => false,
                'deprecated' => true,
            ],
            'devices' => [
                'type' => 'string[]',
                'description' => '',
                'required' => true,
                'deprecated' => true,
            ],
            'teams' => [
                'type' => 'string[]',
                'description' => 'Teams',
                'required' => true,
                'deprecated' => true,
                'example' => ['1', '2'],
            ],
        ], $results);
    }

    protected function fetch($endpoint): array
    {
        $strategy = new GetFromQueryParamAttribute(new DocumentationConfig([]));

        return $strategy($endpoint, []);
    }

    protected function endpoint(\Closure $configure): ExtractedEndpointData
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = []) {}
        };
        $configure($endpoint);

        return $endpoint;
    }
}

#[QueryParam('user_id', description: 'Will be overriden.')]
#[QueryParam('location_id', description: 'The id of the location.')]
class QueryParamAttributeTestController
{
    #[QueryParam('user_id', description: 'The id of the user.', example: 'me')]
    #[QueryParam('page', 'integer', description: 'The page number.', required: false, example: 4)]
    #[QueryParam('with_type', 'number', example: 13.0, required: false)]
    #[QueryParam('with_list_type', type: 'int[]', required: false)]
    #[QueryParam('fields', 'string[]', 'The fields.', required: false, example: ['age', 'name'])]
    #[QueryParam('book_id', 'string', 'Book ID', required: true, deprecated: true)]
    #[QueryParam('team_id', 'string', 'Team ID', required: true, deprecated: true)]
    #[QueryParam('teams', 'string[]', 'Teams', example: ['1', '2'], deprecated: true)]
    #[QueryParam('device', 'object', 'Device', required: false, deprecated: true)]
    #[QueryParam('devices', 'string[]', deprecated: true)]
    #[QueryParam('filters', 'object', 'The filters. ', required: false)]
    #[QueryParam('filters.class', 'double', required: false, example: 11.0, description: 'Class.')]
    #[QueryParam('filters.other', 'string', description: 'Other things.')]
    #[QueryParam('noExampleNoDescription', example: 'No-example')]
    #[QueryParam('noExample', description: 'Something', example: 'No-example')]
    public function methodWithAttributes() {}
}
