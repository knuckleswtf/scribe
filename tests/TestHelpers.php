<?php

namespace Knuckles\Scribe\Tests;

use Illuminate\Contracts\Console\Kernel;

trait TestHelpers
{
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
