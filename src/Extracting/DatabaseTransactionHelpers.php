<?php

namespace Knuckles\Scribe\Extracting;

use Knuckles\Scribe\Exceptions\CouldntStartDatabaseTransaction;
use Knuckles\Scribe\Exceptions\DatabaseTransactionsNotSupported;
use Knuckles\Scribe\Tools\DocumentationConfig;

trait DatabaseTransactionHelpers
{
    /**
     * Returns an instance of the documentation config.
     */
    abstract public function getConfig(): DocumentationConfig;

    private function connectionsToTransact()
    {
        return $this->getConfig()->get('database_connections_to_transact', []);
    }

    private function startDbTransaction()
    {
        foreach ($this->connectionsToTransact() as $connection) {
            $database ??= app('db');

            $driver = $database->connection($connection);

            if (self::driverSupportsTransactions($driver)) {
                try {
                    $driver->beginTransaction();
                } catch (\Throwable $e) {
                    throw CouldntStartDatabaseTransaction::forConnection($connection, $e);
                }
            } else {
                $driverClassName = get_class($driver);

                throw DatabaseTransactionsNotSupported::create($connection, $driverClassName);
            }
        }
    }

    private function endDbTransaction()
    {
        foreach ($this->connectionsToTransact() as $connection) {
            $database ??= app('db');

            $driver = $database->connection($connection);

            try {
                $driver->rollback();
            } catch (\Exception $e) {
                // Any error handling should have been done on the startDbTransaction() side
            }
        }
    }

    private static function driverSupportsTransactions($driver): bool
    {
        $methods = ['beginTransaction', 'rollback'];

        foreach ($methods as $method) {
            if (! method_exists($driver, $method)) {
                return false;
            }
        }

        return true;
    }
}
