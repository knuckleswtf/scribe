<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tools\AnnotationParser;
use PHPUnit\Framework\TestCase;

class AnnotationParserTest extends TestCase
{
    /**
     * @test
     * @dataProvider annotations
     */
    public function can_parse_annotation_into_content_and_attributes(string $annotation, array $expected)
    {
        $result = AnnotationParser::parseIntoContentAndAttributes($annotation, ['status', 'scenario']);

        $this->assertEquals($expected, $result);
    }

    public function annotations()
    {
        return [
            "when attributes come first" => [
                'status=400 scenario="things go wrong" {"message": "failed"}',
                [
                    'attributes' => ['status' => '400', 'scenario' => 'things go wrong'],
                    'content' => '{"message": "failed"}',
                ],
            ],
            "when attributes come last" => [
                '{"message": "failed"} status=400 scenario="things go wrong"',
                [
                    'attributes' => ['status' => '400', 'scenario' => 'things go wrong'],
                    'content' => '{"message": "failed"}',
                ],
            ],
            "when there are no attributes" => [
                '{"message": "failed"} ',
                [
                    'attributes' => ['status' => null, 'scenario' => null],
                    'content' => '{"message": "failed"}',
                ],
            ],
            "when there are some attributes" => [
                ' status=hey {"message": "failed"} ',
                [
                    'attributes' => ['status' => 'hey', 'scenario' => null],
                    'content' => '{"message": "failed"}',
                ],
            ],
        ];
    }
}
