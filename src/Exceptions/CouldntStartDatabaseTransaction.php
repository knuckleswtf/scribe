<?php

namespace Knuckles\Scribe\Exceptions;

class CouldntStartDatabaseTransaction extends \RuntimeException implements ScribeException
{
    public static function forConnection(string $connectionName, \Throwable $originalException): self
    {
        return new self(
            "Couldn't start a database transaction for the connection {$connectionName}. "
            ."Make sure this database is running. If you aren't using this connection, remove it from your `databaseConnectionsToTransact` config",
            0,
            $originalException
        );
    }
}
