<?php

namespace Knuckles\Scribe;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\Globals;
use Symfony\Component\HttpFoundation\Request;

class Scribe
{
    /**
     * Specify a callback that will be executed just before a response call is made
     * (after configuring the environment and starting a transaction).
     *
     * @param callable(Request, ExtractedEndpointData): mixed $callable
     */
    public static function beforeResponseCall(callable $callable)
    {
        Globals::$beforeResponseCall = $callable;
    }
    /**
     * Specify a callback that will be executed when Scribe is done generating your docs.
     * This callback will receive a map of all the output paths generated, that looks like this:
     * [
     *   'postman' => '/absolute/path/to/postman/collection',
     *   'openapi' => '/absolute/path/to/openapi/spec',
     *    // If you're using `laravel` type, `html` will be null, and vice versa for `blade`.
     *   'html' => '/absolute/path/to/index.html/',
     *   'blade' => '/absolute/path/to/blade/view',
     *    // These are paths to asset folders
     *   'assets' => [
     *     'js' => '/path/to/js/assets/folder',
     *     'css' => '/path/to/css/assets/folder',
     *     'images' => '/path/to/images/assets/folder',
     *   ]
     * ]
     *
     * If you disabled `postman` or `openapi`, their values will be null.
     *
     * @param callable $callable
     */
    public static function afterGenerating(callable $callable)
    {
        Globals::$afterGenerating = $callable;
    }
}