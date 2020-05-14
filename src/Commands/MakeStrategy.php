<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeStrategy extends GeneratorCommand
{
    protected $signature = 'scribe:strategy
                            {name : Name of the class.}
                            {stage : The stage the strategy belongs to. One of "metadata", "urlParameters", "queryParameters", "bodyParameters", "headers", "responses", "responseFields".}
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

        return str_replace('dummyStage', $this->argument('stage'), $stub);
    }

}
