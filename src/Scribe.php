<?php

namespace Knuckles\Scribe;

class Scribe
{
    /**
     * Get the middleware for Laravel routes.
     *
     * @return array
     */
    protected static function middleware()
    {
        return config('scribe.laravel.middleware', []);
    }
}
