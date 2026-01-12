<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseUnitTest;
use Knuckles\Scribe\Tools\PathConfig;

/**
 * @internal
 *
 * @coversNothing
 */
class PathConfigurationTest extends BaseUnitTest
{
    /** @test */
    public function resolvesDefaultCachePath()
    {
        $pathConfig = new PathConfig('scribe');
        $this->assertEquals('.scribe', $pathConfig->intermediateOutputPath());
        $this->assertEquals('.scribe/endpoints', $pathConfig->intermediateOutputPath('endpoints'));
        $this->assertEquals('scribe', $pathConfig->outputPath());
        $this->assertEquals('scribe/tim', $pathConfig->outputPath('tim'));
    }

    /** @test */
    public function resolvesCachePathWithSubdirectories()
    {
        $pathConfig = new PathConfig('scribe/bob');
        $this->assertEquals('.scribe/bob', $pathConfig->intermediateOutputPath());
        $this->assertEquals('.scribe/bob/tim', $pathConfig->intermediateOutputPath('tim'));
        $this->assertEquals('scribe/bob', $pathConfig->outputPath());
        $this->assertEquals('scribe/bob/tim', $pathConfig->outputPath('tim'));
    }

    /** @test */
    public function supportsCustomCachePath()
    {
        $pathConfig = new PathConfig('scribe/bob', scribeDir: 'scribe_cache');
        $this->assertEquals('scribe_cache', $pathConfig->intermediateOutputPath());
        $this->assertEquals('scribe_cache/tim', $pathConfig->intermediateOutputPath('tim'));
        $this->assertEquals('scribe/bob', $pathConfig->outputPath());
        $this->assertEquals('scribe/bob/tim', $pathConfig->outputPath('tim'));
    }
}
