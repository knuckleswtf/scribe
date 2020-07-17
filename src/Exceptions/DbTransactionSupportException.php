<?php

namespace Knuckles\Scribe\Exceptions;

use Knuckles\Scribe\Exceptions\ScribeException;
use RuntimeException;

class DbTransactionSupportException extends RuntimeException implements ScribeException
{
    public static function create(string $connection_name, string $driver_name)
    {
        return new self(
            "Database Driver [{$driver_name}] for connection [{$connection_name}] does not support transactions. " .
            "Changes to your database will be persistent. " .
            "To allow this, add \"{$connection_name}\" to the \"run_without_database_transactions\" config."
        );
    }
}
