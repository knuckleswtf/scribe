<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\HtmlWriter;

class HtmlWriterTest extends BaseLaravelTest
{
    /** @test */
    public function sets_last_updated_correctly()
    {
        $config = ["base_url" => "http://local.test", "title" => "API Docs"];
        $config["last_updated"] = '';
        $writer = new HtmlWriter(new DocumentationConfig($config));
        $lastUpdated = $writer->getMetadata()["last_updated"];
        $this->assertEquals('', $lastUpdated);

        $config["last_updated"] = "Last updated on {date:l}";
        $writer = new HtmlWriter(new DocumentationConfig($config));
        $lastUpdated = $writer->getMetadata()["last_updated"];
        $today = date("l");
        $this->assertEquals("Last updated on $today", $lastUpdated);

        $config["last_updated"] = "Last updated on {date:l, jS F} (Git commit {git:short})";
        $writer = new HtmlWriter(new DocumentationConfig($config));
        $lastUpdated = $writer->getMetadata()["last_updated"];
        $date = date("l, jS F");
        $commit = trim(shell_exec('git rev-parse --short HEAD'));
        $this->assertEquals("Last updated on $date (Git commit $commit)", $lastUpdated);
    }
}
