<?php

namespace Knuckles\Scribe\Tools;

use NunoMaduro\Collision\Handler;
use NunoMaduro\Collision\Writer;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Whoops\Exception\Inspector;

class ErrorHandlingUtils
{
    public static function dumpExceptionIfVerbose(\Throwable $e): void
    {
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
        $message = "{$exceptionType} in {$file} at line {$line}: {$message}";
        ConsoleOutputUtils::error($message);
        ConsoleOutputUtils::error('Run this again with the --verbose flag to see the full stack trace.');
    }

    public static function dumpException(\Throwable $e): void
    {
        $output = new ConsoleOutput(OutputInterface::VERBOSITY_VERBOSE);
        $handler = new Handler(new Writer(null, $output));
        $handler->setInspector(new Inspector($e));
        $handler->setException($e);
        $handler->handle();
    }
}
