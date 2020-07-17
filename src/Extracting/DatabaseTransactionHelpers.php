<?php

namespace Knuckles\Scribe\Extracting;

use Exception;
use Knuckles\Scribe\Exceptions\DatabaseTransactionsNotSupported;
use Knuckles\Scribe\Exceptions\ScribeException;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\DocumentationConfig;

trait DatabaseTransactionHelpers
{
    /**
     * @return void
     */
    private function startDbTransaction()
    {
        $connections = array_keys(config('database.connections', []));

        foreach ($connections as $connection) {
            try {
                $driver = app('db')->connection($connection);

                if (self::driverSupportsTransactions($driver)) {
                    $driver->beginTransaction();

                    return;
                }

                if ($this->canAllowDatabasePersistence($connection)) {
                    throw DatabaseTransactionsNotSupported::create($connection, get_class($driver));
                }

                c::warn("Database driver for the connection [{$connection}] does not support transactions. Any changes made to your database will persist.");
            } catch (ScribeException $e) {
                throw $e;
            } catch (Exception $e) {
            }
        }
    }

    /**
     * @return void
     */
    private function endDbTransaction()
    {
        $connections = array_keys(config('database.connections', []));

        foreach ($connections as $connection) {
            try {
                $driver = app('db')->connection($connection);

                if (self::driverSupportsTransactions($driver)) {
                    $driver->rollBack();

                    return;
                }

                c::warn("Database driver for the connection [{$connection}] does not support transactions. Any changes made to your database have been persisted.");
            } catch (Exception $e) {
            }
        }
    }

    /**
     * Assesses whether or not the "PDO" driver provided supports transactions
     *
     * @param mixed $driver Driver prodicted for particular connection
     *
     * @return boolean
     */
    private static function driverSupportsTransactions($driver)
    {
        $methods = ['beginTransaction', 'rollback'];

        foreach ($methods as $method) {
            if (! method_exists($driver, $method)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assesses whether drivers without transaction support can proceed
     *
     * @param string $connection Name of the connection
     *
     * @return boolean
     */
    private function canAllowDatabasePersistence(string $connection)
    {
        $config = $this->getConfig();

        $whitelistedConnections = $config->get('allow_database_persistence', []);
        return in_array($connection, $whitelistedConnections);
    }

    /**
     * Returns an instance of the documentation config
     *
     * @return DocumentationConfig
     */
    abstract public function getConfig();
}
