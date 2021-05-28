<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeStrategy extends GeneratorCommand
{
    protected $signature = 'scribe:strategy
                            {name : Name of the class.}
                            {--force : Overwrite file if it exists}
    ';

    protected $description = 'Create a new strategy class.';

    protected $type = 'Strategy';

    protected function getStub()
    {
        return __DIR__ . '/stubs/strategy.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Docs\Strategies';
    }

    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);

        return $stub;
    }

}
