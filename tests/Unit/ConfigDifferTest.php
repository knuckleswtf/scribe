<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseUnitTest;
use Knuckles\Scribe\Tools\ConfigDiffer;

class ConfigDifferTest extends BaseUnitTest
{
    /** @test */
    public function returns_empty_when_there_are_no_changes()
    {
        $default = [
            'title' => null,
            'theme' => 'default',
            'extra' => 'ignored',
        ];
        $user = [
            'theme' => 'default',
            'title' => null,
        ];
        $differ = new ConfigDiffer($default, $user);
        $diff = $differ->getDiff();
        $this->assertEquals([], $diff);
    }

    /** @test */
    public function works()
    {
        $default = [
            'title' => null,
            'theme' => 'default',
        ];
        $user = [
            'theme' => 'elements',
            'title' => null,
        ];
        $differ = new ConfigDiffer($default, $user);
        $diff = $differ->getDiff();
        $this->assertEquals([
            "theme" => '"elements"',
        ], $diff);
    }

    /** @test */
    public function ignores_specified_paths()
    {
        $default = [
            'theme' => 'default',
            'description' => '',
            'test' => [
                'array' => [ 'old-item' ],
                'string' => null,
            ],
        ];
        $user = [
            'theme' => 'elements',
            'description' => 'Details',
            'test' => [
                'string' => 'value',
                'array' => [ 'new-item' ]
            ],
        ];
        $differ = new ConfigDiffer($default, $user, ignorePaths: ['description', 'test.array']);
        $diff = $differ->getDiff();
        $this->assertEquals([
            "theme" => '"elements"',
            'test.string' => '"value"',
        ], $diff);
    }
}
