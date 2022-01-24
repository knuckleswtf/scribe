<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tools\AnnotationParser;
use PHPUnit\Framework\TestCase;

class AnnotationParserTest extends TestCase
{
    /**
     * @test
     * @dataProvider contentAttributesAnnotations
     */
    public function can_parse_annotation_into_content_and_attributes(string $annotation, array $expected)
    {
        $result = AnnotationParser::parseIntoContentAndAttributes($annotation, ['status', 'scenario']);

        $this->assertEquals($expected, $result);
    }

    public function contentAttributesAnnotations()
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

    /**
     * @test
     * @dataProvider attributesAnnotations
     */
    public function can_parse_annotation_into_attributes(string $annotation, array $expected)
    {
        $result = AnnotationParser::parseIntoAttributes($annotation);

        $this->assertEquals($expected, $result);
    }

    public function attributesAnnotations()
    {
        return [
            "when attributes filled" => [
                'status=400 scenario="things go wrong" "dummy field"="dummy data", "snaked_data"=value',
                [
                    'status' => '400',
                    'scenario' => 'things go wrong',
                    'dummy field' => 'dummy data',
                    'snaked_data' => 'value'
                ]
            ],
            "when attributes not filled" => [
                '{"message": "failed"}',
                []
            ],
            "when attributes wrong" => [
                'status= scenario="things go wrong"',
                [
                    'scenario' => 'things go wrong'
                ]
            ]
        ];
    }
}
