<?php

namespace Knuckles\Scribe\Tests\Strategies\UrlParameters;

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
            new Tag('urlParam', 'withType number With type, maybe.'),
            new Tag('urlParam', 'withTypeDefinitely integer required With type.'),
            new Tag('urlParam', 'barebones'),
            new Tag('urlParam', 'barebonesType number'),
            new Tag('urlParam', 'barebonesRequired required'),
            new Tag('urlParam', 'withExampleOnly Example: 12'),
            new Tag('urlParam', 'withExampleOnlyButTyped int Example: 12'),
            new Tag('urlParam', 'noExampleNoDescription No-example.'),
            new Tag('urlParam', 'noExample Something No-example'),
        ];
        $results = $strategy->getUrlParametersFromDocBlock($tags);

        $this->assertArraySubset([
            'id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'The id of the order.',
            ],
            'lang' => [
                'type' => 'string',
                'required' => false,
                'description' => 'The language to serve in.',
            ],
            'withType' => [
                'type' => 'number',
                'required' => false,
                'description' => 'With type, maybe.',
            ],
            'withTypeDefinitely' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'With type.',
            ],
            'barebones' => [
                'type' => 'string',
                'required' => false,
                'description' => '',
            ],
            'barebonesType' => [
                'type' => 'number',
                'required' => false,
                'description' => '',
            ],
            'barebonesRequired' => [
                'type' => 'string',
                'required' => true,
                'description' => '',
            ],
            'withExampleOnly' => [
                'type' => 'string',
                'required' => false,
                'description' => '',
                'value' => '12',
            ],
            'withExampleOnlyButTyped' => [
                'type' => 'integer',
                'required' => false,
                'description' => '',
                'value' => 12
            ],
            'noExampleNoDescription' => [
                'type' => 'string',
                'required' => false,
                'description' => '',
                'value' => null
            ],
            'noExample' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Something',
                'value' => null
            ],
        ], $results);
    }

}
