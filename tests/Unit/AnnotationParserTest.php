<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseUnitTest;
use Knuckles\Scribe\Tools\AnnotationParser;

class AnnotationParserTest extends BaseUnitTest
{
    /**
     * @test
     * @dataProvider annotationsWithContentAndFields
     */
    public function can_parse_annotation_into_content_and_fields(string $annotation, array $expected)
    {
        $result = AnnotationParser::parseIntoContentAndFields($annotation, ['status', 'scenario']);

        $this->assertEquals($expected, $result);
    }

    public static function annotationsWithContentAndFields()
    {
        return [
            "when fields come first" => [
                'status=400 scenario="things go wrong" {"message": "failed"}',
                [
                    'fields' => ['status' => '400', 'scenario' => 'things go wrong'],
                    'content' => '{"message": "failed"}',
                ],
            ],
            "when fields come last" => [
                '{"message": "failed"} status=400 scenario="things go wrong"',
                [
                    'fields' => ['status' => '400', 'scenario' => 'things go wrong'],
                    'content' => '{"message": "failed"}',
                ],
            ],
            "when there are no fields" => [
                '{"message": "failed"} ',
                [
                    'fields' => ['status' => null, 'scenario' => null],
                    'content' => '{"message": "failed"}',
                ],
            ],
            "when there are some fields" => [
                ' status=hey {"message": "failed"} ',
                [
                    'fields' => ['status' => 'hey', 'scenario' => null],
                    'content' => '{"message": "failed"}',
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider annotationsWithFields
     */
    public function can_parse_annotation_into_fields(string $annotation, array $expected)
    {
        $result = AnnotationParser::parseIntoFields($annotation);

        $this->assertEquals($expected, $result);
    }

    public static function annotationsWithFields()
    {
        return [
            "with or without quotes" => [
                'title=This message="everything good" "dummy field"="dummy data", "snaked_data"=value',
                [
                    'title' => 'This',
                    'message' => "everything good",
                    'dummy field' => 'dummy data',
                    'snaked_data' => 'value'
                ]
            ],
            "no fields" => [
                '{"message": "failed"}',
                []
            ],
            "fields with empty values" => [
                'title= message="everything good"',
                [
                    'message' => 'everything good'
                ]
            ]
        ];
    }
}
