<?php

namespace Knuckles\Scribe\Tests\Strategies\Headers;

use Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderTag;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class GetFromHeaderTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_header_tag()
    {
        $strategy = new GetFromHeaderTag(new DocumentationConfig([]));
        $tags = [
            new Tag('header', 'Api-Version v1'),
            new Tag('header', 'Some-Custom'),
        ];
        $results = $strategy->getHeadersFromDocBlock($tags);

        $this->assertArraySubset([
            'Api-Version' => 'v1',
        ], $results);

        $this->assertArrayHasKey('Some-Custom', $results);
        $this->assertNotEmpty($results['Some-Custom']);
    }

}
