<?php

namespace Knuckles\Scribe\Exceptions;

use Knuckles\Scribe\Exceptions\ScribeException;
use RuntimeException;

class DatabaseTransactionsNotSupported extends RuntimeException implements ScribeException
{
    public static function create(string $connectionName, string $driverName)
    {
        return new self(
            "Database Driver [{$driverName}] for connection [{$connectionName}] does not support transactions. " .
            "Changes to your database will be persistent. " .
            "To allow this, add \"{$driverName}\" to the \"continue_without_database_transactions\" config."
        );
    }
}
