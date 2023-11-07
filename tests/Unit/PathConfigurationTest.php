<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Configuration\PathConfig;
use PHPUnit\Framework\TestCase;

class PathConfigurationTest extends TestCase
{
    /** @test */
    public function object_resolves_into_hidden_string()
    {
        $pathConfig = new PathConfig('scribe', 'scribe', true);
        $this->assertEquals('.scribe', $pathConfig->getTemporaryDirectoryPath());
    }

    /** @test */
    public function object_resolves_into_non_hidden_string()
    {
        $pathConfig = new PathConfig('scribe', 'scribe', false);
        $this->assertEquals('scribe', $pathConfig->getTemporaryDirectoryPath());
    }

    /** @test */
    public function object_resolves_into_hidden_string_for_subdirs()
    {
        $pathConfig = new PathConfig('scribe/bob', 'scribe', true);
        $this->assertEquals('.scribe/bob', $pathConfig->getTemporaryDirectoryPath());
    }

    /** @test */
    public function object_resolves_into_non_hidden_string_for_subdirs()
    {
        $pathConfig = new PathConfig('scribe/bob/dave', 'scribe', false);
        $this->assertEquals('scribe/bob/dave', $pathConfig->getTemporaryDirectoryPath());
    }
}
