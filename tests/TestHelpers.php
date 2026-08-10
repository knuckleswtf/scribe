<?php

namespace Knuckles\Scribe\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Pagination\Paginator;

trait TestHelpers
{
    /**
     * The `meta` block of a response paginated with the simple paginator. Laravel started
     * adding `current_page_url` to it during the 12.x branch, so the shape can't be
     * hardcoded while the test matrix spans Laravel 9 to 13. Detected from the paginator
     * itself rather than from a version number, since it landed in a patch release.
     */
    protected function paginationMeta(array $meta): array
    {
        if (! array_key_exists('current_page_url', (new Paginator([], 1))->toArray())) {
            return $meta;
        }

        $withCurrentPageUrl = [];
        foreach ($meta as $key => $value) {
            $withCurrentPageUrl[$key] = $value;
            if ($key === 'current_page') {
                $withCurrentPageUrl['current_page_url'] = $meta['path'].'?page='.$value;
            }
        }

        return $withCurrentPageUrl;
    }

    /**
     * @param  string  $command
     * @param  array  $parameters
     * @return mixed
     */
    public function artisan($command, $parameters = [])
    {
        /** @var Kernel $kernel */
        $kernel = $this->app[Kernel::class];
        $kernel->call($command, $parameters);

        return $kernel->output();
    }

    protected function generate(array $flags = []): mixed
    {
        return $this->artisan(
            'scribe:generate',
            array_merge(['--no-upgrade-check' => true], $flags)
        );
    }

    protected function generateAndExpectConsoleOutput(
        array $options = [],
        array $expected = [],
        array $notExpected = [],
    ): void {
        $output = $this->generate($options);

        foreach ($expected as $string) {
            $this->assertStringContainsString($string, $output);
        }

        foreach ($notExpected as $string) {
            $this->assertStringNotContainsString($string, $output);
        }
    }

    protected function assertFileContainsString(string $filePath, string $string)
    {
        $fileContents = file_get_contents($filePath);
        $this->assertStringContainsString($string, $fileContents);
    }

    protected function assertFileNotContainsString(string $filePath, string $string)
    {
        $fileContents = file_get_contents($filePath);
        $this->assertStringNotContainsString($string, $fileContents);
    }
}
