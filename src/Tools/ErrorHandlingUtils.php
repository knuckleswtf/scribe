<?php

namespace Knuckles\Scribe\Tools;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ErrorHandlingUtils
{
    public static function dumpExceptionIfVerbose(\Throwable $e, $completelySilent = false): void
    {
        if ($completelySilent) {
            return;
        }

        if (Globals::$shouldBeVerbose) {
            self::dumpException($e);
            return;
        }
        [$firstFrame, $secondFrame] = $e->getTrace();

        try {
            ['file' => $file, 'line' => $line] = $firstFrame;
        } catch (\Exception $_) {
            ['file' => $file, 'line' => $line] = $secondFrame;
        }
        $exceptionType = get_class($e);
        $message = $e->getMessage();
        $message = "$exceptionType in $file at line $line: $message";
        ConsoleOutputUtils::error($message);
        ConsoleOutputUtils::error('Run this again with the --verbose flag to see the full stack trace.');
    }

    public static function dumpException(\Throwable $e): void
    {
        $output = new ConsoleOutput(OutputInterface::VERBOSITY_VERBOSE);
        $handler = new \NunoMaduro\Collision\Handler(new \NunoMaduro\Collision\Writer(null, $output));
        $handler->setInspector(new \Whoops\Exception\Inspector($e));
        $handler->setException($e);
        $handler->handle();
    }
}
