<?php

namespace Knuckles\Scribe\Tests\Strategies\ResponseFields;

use Closure;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class GetFromResponseFieldTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_responsefield_tag()
    {
        $tags = [
            new Tag('responseField', 'id int The id of the newly created user.'),
            new Tag('responseField', 'other string'),
        ];
        $results = $this->fetch($tags);

        $this->assertArraySubset([
            'id' => [
                'type' => 'integer',
                'description' => 'The id of the newly created user.',
            ],
            'other' => [
                'type' => 'string',
                'description' => '',
            ],
        ], $results);
    }

    /** @test */
    public function can_infer_type_from_first_2xx_response()
    {
        $responses = [
            [
                'status' => 400,
                'content' => json_encode(['id' => 6.4]),
            ],
            [
                'status' => 200,
                'content' => json_encode(['id' => 6]),
            ],
            [
                'status' => 201,
                'content' => json_encode(['id' => 'haha']),
            ],
        ];
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) use ($responses) {
            $e->responses = new ResponseCollection($responses);
        });
        $tags = [
            new Tag('responseField', 'id The id of the newly created user.'),
        ];
        $results = $this->fetch($tags, $endpoint);

        $this->assertArraySubset([
            'id' => [
                'type' => 'integer',
                'description' => 'The id of the newly created user.',
            ],
        ], $results);
    }

    /** @test */
    public function can_infer_type_from_first_2xx_response_for_lists()
    {
        $responses = [
            [
                'status' => 200,
                'content' => json_encode([['id' => 6]]),
            ],
        ];
        $tags = [
            new Tag('responseField', 'id The id of the newly created user.'),
        ];
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) use ($responses) {
            $e->responses = new ResponseCollection($responses);
        });
        $results = $this->fetch($tags, $endpoint);

        $this->assertArraySubset([
            'id' => [
                'type' => 'integer',
                'description' => 'The id of the newly created user.',
            ],
        ], $results);
    }

    /** @test */
    public function defaults_to_nothing_when_type_inference_fails()
    {
        $tags = [
            new Tag('responseField', 'id The id of the newly created user.'),
        ];
        $results = $this->fetch($tags);

        $this->assertArraySubset([
            'id' => [
                'type' => '',
                'description' => 'The id of the newly created user.',
            ],
        ], $results);
    }

    protected function fetch($tags, $endpoint = null): array
    {
        $strategy = new GetFromResponseFieldTag(new DocumentationConfig([]));
        $strategy->endpointData = $endpoint ?: $this->endpoint(function (ExtractedEndpointData $e) {
            $e->responses = new ResponseCollection([]);
        });
        return $strategy->getFromTags($tags);
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
