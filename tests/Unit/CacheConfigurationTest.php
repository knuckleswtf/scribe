<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Configuration\CacheConfiguration;
use PHPUnit\Framework\TestCase;

class CacheConfigurationTest extends TestCase
{
    /** @test */
    public function object_coerses_into_hidden_string()
    {
        $cacheConfiguration = new CacheConfiguration('scribe', 'scribe', true);
        $this->assertEquals('.scribe', $cacheConfiguration);
    }

    /** @test */
    public function object_coerses_into_non_hidden_string()
    {
        $cacheConfiguration = new CacheConfiguration('scribe', 'scribe', false);
        $this->assertEquals('scribe', $cacheConfiguration);
    }

    /** @test */
    public function object_coerses_into_hidden_string_for_subdirs()
    {
        $cacheConfiguration = new CacheConfiguration('scribe/bob', 'scribe', true);
        $this->assertEquals('.scribe/bob', $cacheConfiguration);
    }

    /** @test */
    public function object_coerses_into_non_hidden_string_for_subdirs()
    {
        $cacheConfiguration = new CacheConfiguration('scribe/bob/dave', 'scribe', false);
        $this->assertEquals('scribe/bob/dave', $cacheConfiguration);
    }
}
