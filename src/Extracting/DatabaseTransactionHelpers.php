<?php

namespace Knuckles\Scribe\Extracting;

use Knuckles\Scribe\Exceptions\DatabaseTransactionsNotSupported;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PDOException;

trait DatabaseTransactionHelpers
{
    private function connectionsToTransact()
    {
        return $this->getConfig()->get('database_connections_to_transact', []);
    }

    private function startDbTransaction()
    {
        $database = app('db');

        foreach ($this->connectionsToTransact() as $connection) {
            $driver = $database->connection($connection);

            if (self::driverSupportsTransactions($driver)) {
                try {
                    $driver->beginTransaction();
                } catch (PDOException $e) {
                    throw new \Exception(
                        "Failed to connect to database connection '$connection'." .
                        " Is the database running?" .
                        " If you aren't using this database, remove it from the `database_connections_to_transact` config array."
                    );
                }
                continue;
            } else {
                $driverClassName = get_class($driver);
                throw DatabaseTransactionsNotSupported::create($connection, $driverClassName);
            }
        }
    }

    /**
     * @return void
     */
    private function endDbTransaction()
    {
        $database = app('db');

        foreach ($this->connectionsToTransact() as $connection) {
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
            if (!method_exists($driver, $method)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns an instance of the documentation config
     *
     * @return DocumentationConfig
     */
    abstract public function getConfig(): DocumentationConfig;
}
