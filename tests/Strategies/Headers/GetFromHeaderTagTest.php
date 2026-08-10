<?php

namespace Knuckles\Scribe\Tests\Strategies\Headers;

use Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderTag;
use Knuckles\Scribe\Tests\ArraySubsetAsserts;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class GetFromHeaderTagTest extends TestCase
{
    use ArraySubsetAsserts;

    public function test_can_fetch_from_header_tag()
    {
        $strategy = new GetFromHeaderTag(new DocumentationConfig([]));
        $tags = [
            new Tag('header', 'Api-Version v1'),
            new Tag('header', 'Some-Custom'),
        ];
        $results = $strategy->getFromTags($tags);

        $this->assertArraySubset([
            'Api-Version' => 'v1',
        ], $results);

        $this->assertArrayHasKey('Some-Custom', $results);
        $this->assertNotEmpty($results['Some-Custom']);
    }
}
