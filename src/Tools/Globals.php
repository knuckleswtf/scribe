<?php

namespace Knuckles\Scribe\Tools;

class Globals
{
    public static bool $shouldBeVerbose = false;

    /*
     *  Hooks, used by users to configure Scribe's behaviour.
     */

    public static $__beforeResponseCall;

    public static $__afterGenerating;

    public static $__instantiateFormRequestUsing;
}
