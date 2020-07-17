<?php

namespace Knuckles\Scribe\Exceptions;

use Knuckles\Scribe\Exceptions\ScribeException;
use RuntimeException;

class DatabaseTransactionsNotSupported extends RuntimeException implements ScribeException
{
    public static function create(string $connection, string $driver)
    {
        return new self(
            "Database Driver [{$driver}] for connection [{$connection}] does not support transactions. " .
            "Changes to your database will be persistent. " .
            "To allow this, add \"{$connection}\" to the \"allow_database_persistence\" config."
        );
    }
}
