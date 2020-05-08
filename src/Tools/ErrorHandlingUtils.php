<?php

namespace Knuckles\Scribe\Tools;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ErrorHandlingUtils
{
    public static function dumpException(\Throwable $e): void
    {
        if (!class_exists(\NunoMaduro\Collision\Handler::class)) {
            dump($e);
            ConsoleOutputUtils::info("You can get better exception output by installing the library nunomaduro/collision.");
            return;
        }

        $output = new ConsoleOutput(OutputInterface::VERBOSITY_VERBOSE);
        try {
            $handler = new \NunoMaduro\Collision\Handler(new \NunoMaduro\Collision\Writer(null, $output));
        } catch (\Exception $e) {
            // Version 3 used a different API
            $handler = new \NunoMaduro\Collision\Handler(new \NunoMaduro\Collision\Writer($output));
        }
        $handler->setInspector(new \Whoops\Exception\Inspector($e));
        $handler->setException($e);
        $handler->handle();

    }
}
