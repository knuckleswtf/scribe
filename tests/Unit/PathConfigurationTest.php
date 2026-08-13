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
    public function test_resolves_default_cache_path()
    {
        $pathConfig = new PathConfig('scribe');
        $this->assertEquals('.scribe', $pathConfig->intermediateOutputPath());
        $this->assertEquals('.scribe/endpoints', $pathConfig->intermediateOutputPath('endpoints'));
        $this->assertEquals('scribe', $pathConfig->outputPath());
        $this->assertEquals('scribe/tim', $pathConfig->outputPath('tim'));
    }

    public function test_resolves_cache_path_with_subdirectories()
    {
        $pathConfig = new PathConfig('scribe/bob');
        $this->assertEquals('.scribe/bob', $pathConfig->intermediateOutputPath());
        $this->assertEquals('.scribe/bob/tim', $pathConfig->intermediateOutputPath('tim'));
        $this->assertEquals('scribe/bob', $pathConfig->outputPath());
        $this->assertEquals('scribe/bob/tim', $pathConfig->outputPath('tim'));
    }

    public function test_supports_custom_cache_path()
    {
        $pathConfig = new PathConfig('scribe/bob', scribeDir: 'scribe_cache');
        $this->assertEquals('scribe_cache', $pathConfig->intermediateOutputPath());
        $this->assertEquals('scribe_cache/tim', $pathConfig->intermediateOutputPath('tim'));
        $this->assertEquals('scribe/bob', $pathConfig->outputPath());
        $this->assertEquals('scribe/bob/tim', $pathConfig->outputPath('tim'));
    }
}
