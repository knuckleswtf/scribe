<?php

namespace Knuckles\Scribe\Tests\Extracting\Strategies\Responses;

use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Orchestra\Testbench\TestCase;

class UseResponseFileTagTest extends TestCase
{
    use ArraySubsetAsserts;

    protected function getPackageProviders($app)
    {
        return [
            ScribeServiceProvider::class,
        ];
    }

    /** @test */
    public function can_fetch_from_responsefile_tag()
    {
        $filePath = __DIR__ . '/../../../Fixtures/response_test.json';
        copy($filePath, storage_path('response_test.json'));

        $strategy = new UseResponseFileTag(new DocumentationConfig([]));
        $tags = [
            new Tag('responseFile', 'response_test.json'),
        ];
        $results = $strategy->getFileResponses($tags);

        $fixtureFileJson = file_get_contents($filePath);
        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => $fixtureFileJson,
            ],
        ], $results);

        unlink(storage_path('response_test.json'));
    }

    /** @test */
    public function allows_multiple_responsefile_tags_for_multiple_statuses()
    {
        $filePath = __DIR__ . '/../../../Fixtures/response_test.json';
        $filePath2 = __DIR__ . '/../../../Fixtures/response_error_test.json';
        copy($filePath, storage_path('response_test.json'));
        copy($filePath2, storage_path('response_error_test.json'));

        $strategy = new UseResponseFileTag(new DocumentationConfig([]));
        $tags = [
            new Tag('responseFile', '200 response_test.json'),
            new Tag('responseFile', '401 response_error_test.json'),
        ];
        $results = $strategy->getFileResponses($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => file_get_contents($filePath),
            ],
            [
                'status' => 401,
                'content' => file_get_contents($filePath2),
            ],
        ], $results);

        unlink(storage_path('response_test.json'));
        unlink(storage_path('response_error_test.json'));
    }

    /** @test */
    public function can_add_or_replace_key_value_pair_in_response_file()
    {

        $filePath = __DIR__ . '/../../../Fixtures/response_test.json';
        copy($filePath, storage_path('response_test.json'));

        $strategy = new UseResponseFileTag(new DocumentationConfig([]));
        $tags = [
            new Tag('responseFile', 'response_test.json {"message" : "Serendipity", "gender": "male"}'),
        ];
        $results = $strategy->getFileResponses($tags);

        $this->assertArraySubset([
            [
                'status' => 200,
                'content' => '{"id":5,"name":"Jessica Jones","gender":"male","message":"Serendipity"}',
            ],
        ], $results);
    }
}
