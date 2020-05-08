<?php

namespace Knuckles\Scribe\Tools;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ErrorHandlingUtils
{
    public static function dumpExceptionIfVerbose(\Throwable $e): void
    {
        if (Flags::$shouldBeVerbose) {
            self::dumpException($e);
        } else {
            ConsoleOutputUtils::warn(get_class($e) . ': ' . $e->getMessage());
            ConsoleOutputUtils::warn('Run again with --verbose for a full stacktrace');
        }

    }

    public static function dumpException(\Throwable $e): void
    {
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
