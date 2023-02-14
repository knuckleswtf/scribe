<?php

namespace Knuckles\Scribe\Exceptions;

class CouldntGetRouteDetails extends \RuntimeException implements ScribeException
{
    public static function forRoute(string $route): self
    {
        return new self("Unable to retrieve controller and method for route $route; try running `php artisan route:clear`");
    }
}
