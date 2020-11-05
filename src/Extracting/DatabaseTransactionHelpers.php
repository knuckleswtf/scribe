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


        foreach (config('scribe.enable_transactions_for_database_connections', [config('database.default')]) as $connection) {
            try {
                $driver = app('db')->connection($connection);

                if (self::driverSupportsTransactions($driver)) {
                    $driver->beginTransaction();

                    continue;
                }

                $driverClassName = get_class($driver);
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
        foreach (config('scribe.enable_transactions_for_database_connections', [config('database.default')]) as $connection) {

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
    abstract public function getConfig();

}
