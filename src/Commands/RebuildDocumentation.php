<?php

namespace Mpociot\ApiDoc\Commands;

use Illuminate\Console\Command;
use Mpociot\ApiDoc\Tools\DocumentationConfig;
use Mpociot\ApiDoc\Tools\Flags;
use Mpociot\ApiDoc\Writing\Writer;
use Shalvah\Clara\Clara;

class RebuildDocumentation extends Command
{
    protected $signature = 'apidoc:rebuild';

    protected $description = 'Rebuild your API documentation from your markdown file.';

    /**
     * @var Clara
     */
    private $clara;

    public function handle()
    {
        Flags::$shouldBeVerbose = $this->option('verbose');
        $this->clara = clara('knuckleswtf/scribe',  Flags::$shouldBeVerbose)->only();

        $sourceOutputPath = 'resources/docs/source';
        if (! is_dir($sourceOutputPath)) {
            $this->clara->error('There is no existing documentation available at ' . $sourceOutputPath . '.');

            return false;
        }

        $this->clara->info('Rebuilding API documentation from ' . $sourceOutputPath . '/index.md');

        $writer = new Writer(new DocumentationConfig(config('apidoc')));
        $writer->writeHtmlDocs();
    }
}
