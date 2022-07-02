<?php

namespace Knuckles\Scribe\Tools;

class Globals
{
    public const SCRIBE_VERSION = '4.x-dev';

    public static bool $shouldBeVerbose = false;

    /*
     *  Hooks, used by users to configure Scribe's behaviour.
     */

    public static $__beforeResponseCall;

    public static $__afterGenerating;

    public static $__instantiateFormRequestUsing;
}
