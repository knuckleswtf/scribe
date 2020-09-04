<?php

namespace Knuckles\Scribe\Extracting;

use Exception;
use Knuckles\Scribe\Exceptions\DatabaseTransactionsNotSupported;
use Knuckles\Scribe\Exceptions\ScribeException;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\DocumentationConfig;

trait DatabaseTransactionHelpers
{
    private function startDbTransaction()
    {
        $connections = array_keys(config('database.connections', []));

        foreach ($connections as $connection) {
            try {
                $driver = app('db')->connection($connection);

                if (self::driverSupportsTransactions($driver)) {
                    $driver->beginTransaction();

                    continue;
                }

                $driverClassName = get_class($driver);

                if ($this->shouldAllowDatabasePersistence($driverClassName)) {
                    throw DatabaseTransactionsNotSupported::create($connection, $driverClassName);
                }

                c::warn("Database driver [$driverClassName] for the connection [{$connection}] does not support transactions. Any changes made to your database will persist.");
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

                    continue;
                }

                $driverClassName = get_class($driver);
                c::warn("Database driver [$driverClassName] for the connection [{$connection}] does not support transactions. Any changes made to your database have been persisted.");
            } catch (Exception $e) {
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

    /**
     * Assesses whether drivers without transaction support can proceed
     *
     * @param string $driverClassName
     *
     * @return bool
     */
    private function shouldAllowDatabasePersistence(string $driverClassName): bool
    {
        $config = $this->getConfig();

        $whitelistedDrivers = $config->get('continue_without_database_transactions', []);
        return in_array($driverClassName, $whitelistedDrivers);
    }

    /**
     * Returns an instance of the documentation config
     *
     * @return DocumentationConfig
     */
    abstract public function getConfig();
}
