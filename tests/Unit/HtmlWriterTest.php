<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\HtmlWriter;

/**
 * @internal
 *
 * @coversNothing
 */
class HtmlWriterTest extends BaseLaravelTest
{
    public function test_sets_last_updated_correctly()
    {
        $config = ['base_url' => 'http://local.test', 'title' => 'API Docs'];
        $config['last_updated'] = '';
        $writer = new HtmlWriter(new DocumentationConfig($config));
        $lastUpdated = $writer->getMetadata()['last_updated'];
        $this->assertEquals('', $lastUpdated);

        $config['last_updated'] = 'Last updated on {date:l}';
        $writer = new HtmlWriter(new DocumentationConfig($config));
        $lastUpdated = $writer->getMetadata()['last_updated'];
        $today = date('l');
        $this->assertEquals("Last updated on {$today}", $lastUpdated);

        $config['last_updated'] = 'Last updated on {date:l, jS F} (Git commit {git:short})';
        $writer = new HtmlWriter(new DocumentationConfig($config));
        $lastUpdated = $writer->getMetadata()['last_updated'];
        $date = date('l, jS F');
        $commit = mb_trim(shell_exec('git rev-parse --short HEAD'));
        $this->assertEquals("Last updated on {$date} (Git commit {$commit})", $lastUpdated);
    }

    public function test_renders_blade_syntax_in_base_url()
    {
        config()->set('app.url', 'https://resolved.example.com');

        $writer = new HtmlWriter(new DocumentationConfig([
            'base_url' => "{{ config('app.url') }}/api",
            'title' => 'API Docs',
        ]));

        $baseUrl = (new \ReflectionClass($writer))->getProperty('baseUrl')->getValue($writer);
        $this->assertEquals('https://resolved.example.com/api', $baseUrl);
    }

    public function test_leaves_plain_base_url_untouched()
    {
        $writer = new HtmlWriter(new DocumentationConfig([
            'base_url' => 'https://plain.example.com',
            'title' => 'API Docs',
        ]));

        $baseUrl = (new \ReflectionClass($writer))->getProperty('baseUrl')->getValue($writer);
        $this->assertEquals('https://plain.example.com', $baseUrl);
    }
}
