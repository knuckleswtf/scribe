<?php

namespace Mpociot\ApiDoc\Commands;

use Illuminate\Console\Command;
use Mpociot\ApiDoc\Tools\DocumentationConfig;
use Mpociot\ApiDoc\Tools\Flags;
use Mpociot\ApiDoc\Writing\Writer;
use Shalvah\Clara\Clara;

class UpdateDocumentation extends Command
{
    protected $signature = 'scribe:update';

    protected $description = 'Update your API documentation with changes made to your markdown files.';

    /**
     * @var Clara
     */
    private $clara;

    public function __construct()
    {
        parent::__construct();
        $this->setAliases(['scribe:rebuild']);
    }

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
