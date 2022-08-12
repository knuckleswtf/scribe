<?php

namespace Knuckles\Scribe\Tests;

use Illuminate\Contracts\Console\Kernel;

trait TestHelpers
{
    /**
     * @param string $command
     * @param array $parameters
     *
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
            'scribe:generate', array_merge(['--no-upgrade-check' => true], $flags)
        );
    }

    protected function generateAndExpectConsoleOutput(string ...$expectedOutput): void
    {
        $output = $this->generate();

        foreach ($expectedOutput as $expected) {
            $this->assertStringContainsString($expected, $output);
        }
    }

    protected function assertFileContainsString(string $filePath, string $string)
    {
        $this->assertFileExists($filePath);
        $fileContents = file_get_contents($filePath);
        $this->assertStringContainsString($string, $fileContents);
    }
}
