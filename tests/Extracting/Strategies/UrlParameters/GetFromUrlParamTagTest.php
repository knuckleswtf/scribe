<?php

namespace Knuckles\Scribe\Tests\Extracting\Strategies\UrlParameters;

use Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamTag;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Mpociot\Reflection\DocBlock\Tag;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class GetFromUrlParamTagTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_urlparam_tag()
    {
        $strategy = new GetFromUrlParamTag(new DocumentationConfig([]));
        $tags = [
            new Tag('urlParam', 'id required The id of the order.'),
            new Tag('urlParam', 'lang The language to serve in.'),
        ];
        $results = $strategy->getUrlParametersFromDocBlock($tags);

        $this->assertArraySubset([
            'id' => [
                'required' => true,
                'description' => 'The id of the order.',
            ],
            'lang' => [
                'required' => false,
                'description' => 'The language to serve in.',
            ],
        ], $results);
    }

}
