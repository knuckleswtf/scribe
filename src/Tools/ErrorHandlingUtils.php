<?php

namespace Knuckles\Scribe\Tools;

use Amp\MultiReasonException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ErrorHandlingUtils
{
    public static function dumpExceptionIfVerbose(\Throwable $e, $completelySilent = false): void
    {
        if (Flags::$shouldBeVerbose) {
            if ($e instanceof MultiReasonException) {
                self::dumpException($e->getReasons()[0]);
            } else {
                self::dumpException($e);
            }
        } else if (!$completelySilent) {
            if ($e instanceof MultiReasonException) {
                $message = join("\n", array_map(function (\Throwable $reason) {
                    return get_class($reason).": ".$reason->getMessage();
                }, $e->getReasons()));
            } else {
                [$firstFrame, $secondFrame] = $e->getTrace();

                try {
                    ['file' => $file, 'line' => $line] = $firstFrame;
                } catch (\Exception $_) {
                    ['file' => $file, 'line' => $line] = $secondFrame;
                }
                $exceptionType = get_class($e);
                $message = $e->getMessage();
                $message = "$exceptionType in $file at line $line: $message";
            }
            ConsoleOutputUtils::warn($message);
            ConsoleOutputUtils::warn('Run this again with the --verbose flag to see the full stack trace.');
        }

    }

    public static function dumpException(\Throwable $e): void
    {
        $output = new ConsoleOutput(OutputInterface::VERBOSITY_VERBOSE);
        try {
            $handler = new \NunoMaduro\Collision\Handler(new \NunoMaduro\Collision\Writer(null, $output));
        } catch (\Exception $e) {
            // Version 3 used a different API
            // todo remove when Laravel 7 is minimum supported
            $handler = new \NunoMaduro\Collision\Handler(new \NunoMaduro\Collision\Writer($output));
        }
        $handler->setInspector(new \Whoops\Exception\Inspector($e));
        $handler->setException($e);
        $handler->handle();

    }
}
