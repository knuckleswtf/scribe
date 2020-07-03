<?php

namespace Knuckles\Scribe\Tools;

use Illuminate\Routing\Route;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleOutputUtils
{
    /**
     * @var \Shalvah\Clara\Clara|null
     */
    private static $clara = null;

    public static function bootstrapOutput(OutputInterface $outputInterface)
    {
        $showDebug = Flags::$shouldBeVerbose;
        self::$clara = clara('knuckleswtf/scribe', $showDebug)
            ->useOutput($outputInterface)
            ->only();
    }

    public static function warn($message)
    {
        if (!self::$clara) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$clara->warn($message);
    }

    public static function info($message)
    {
        if (!self::$clara) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$clara->info($message);
    }

    public static function debug($message)
    {
        if (!self::$clara) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$clara->debug($message);
    }

    public static function success($message)
    {
        if (!self::$clara) {
            self::bootstrapOutput(new ConsoleOutput);
        }
        self::$clara->success($message);
    }

    /**
     * Return a string representation of a route to output to the console eg [GET] /api/users
     * @param Route $route
     *
     * @return string
     */
    public static function getRouteRepresentation(Route $route): string
    {
        $methods = $route->methods();
        if (count($methods) > 1) {
            $methods = array_diff($route->methods(), ['HEAD']);
        }

        $routeMethods = implode(',', $methods);
        $routePath = $route->uri();
        return "[$routeMethods] $routePath";
    }
}
