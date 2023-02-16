<?php

namespace Knuckles\Scribe\Exceptions;

class CouldntGetRouteDetails extends \RuntimeException implements ScribeException
{
    public static function new(): self
    {
        return new self("Unable to retrieve controller and method for route; try running `php artisan route:clear`");
    }
}
