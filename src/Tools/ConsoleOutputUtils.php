<?php

namespace Knuckles\Scribe\Tools;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Commands\GenerateDocumentation;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleOutputUtils
{
    /**
     * @var null|OutputInterface
     */
    private static $output;

    /**
     * @var null|GenerateDocumentation
     */
    private static $command;

    /**
     * @var array
     */
    private static $warningBuffer = [];

    /**
     * @var bool
     */
    private static $isBufferingWarnings = false;

    public static function bootstrapOutput(OutputInterface $outputInterface): void
    {
        self::$output = $outputInterface;
    }

    public static function setCommand(?GenerateDocumentation $command): void
    {
        self::$command = $command;
    }

    public static function startWarningBuffer(): void
    {
        self::$isBufferingWarnings = true;
        self::$warningBuffer = [];
    }

    public static function flushWarningBuffer(): void
    {
        self::$isBufferingWarnings = false;
        foreach (self::$warningBuffer as $warning) {
            self::warn($warning);
        }
        self::$warningBuffer = [];
    }

    /**
     * Run a task with Laravel-style output (dots, timing, status).
     */
    public static function task(string $description, callable $task): mixed
    {
        if (self::$command) {
            // Laravel's task() method doesn't return the callback result, so we capture it.
            $result = null;
            self::$command->outputComponents()->task($description, function () use (&$result, $task) {
                $result = $task();
            });

            return $result;
        }

        // Fallback for contexts without command
        self::info($description);
        $result = $task();
        self::success($description);

        return $result;
    }

    public static function deprecated(string $feature, string $inVersion, ?string $should = null): void
    {
        $message = "You're using {$feature}. This is deprecated and will be removed in the next major version.";
        if ($should) {
            $message .= "\nYou should {$should} instead.";
        }
        $message .= " See the changelog for details (v{$inVersion}).";

        self::warn($message);
    }

    public static function warn(string $message): void
    {
        if (self::$isBufferingWarnings) {
            self::$warningBuffer[] = $message;

            return;
        }

        if (self::$command) {
            self::$command->outputComponents()->warn($message);

            return;
        }

        if (! self::$output) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$output->writeln("<fg=yellow>  ⚠ {$message}</>");
    }

    public static function info(string $message): void
    {
        if (self::$command) {
            self::$command->outputComponents()->info($message);

            return;
        }

        if (! self::$output) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$output->writeln("<fg=gray>  ℹ {$message}</>");
    }

    public static function debug(string $message): void
    {
        if (! Globals::$shouldBeVerbose) {
            return;
        }

        if (! self::$output) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$output->writeln("<fg=gray>  🐛 {$message}</>");
    }

    public static function success(string $message): void
    {
        if (! self::$output) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$output->writeln("<fg=green>  ✔ {$message}</>");
    }

    public static function error(string $message): void
    {
        if (self::$command) {
            self::$command->outputComponents()->error($message);

            return;
        }

        if (! self::$output) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$output->writeln("<fg=red>  ✖ {$message}</>");
    }

    /**
     * Return a string representation of a route to output to the console eg [GET] /api/users.
     */
    public static function getRouteRepresentation(Route $route): string
    {
        $methods = $route->methods();
        if (count($methods) > 1) {
            $methods = array_diff($route->methods(), ['HEAD']);
        }

        $routeMethods = implode('|', $methods);
        $routePath = $route->uri();

        return "[<fg=cyan>{$routeMethods}</>] {$routePath}";
    }
}
