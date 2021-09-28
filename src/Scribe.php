<?php

namespace Knuckles\Scribe;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\Globals;
use Symfony\Component\HttpFoundation\Request;

class Scribe
{
    /**
     * @param callable(Request, ExtractedEndpointData): mixed $callable
     */
    public static function beforeResponseCall(callable $callable)
    {
        Globals::$beforeResponseCall = $callable;
    }
}