<?php

namespace Knuckles\Scribe\Extracting;

use Exception;

trait DatabaseTransactionHelpers
{
    /**
     * @return void
     */
    private function startDbTransaction()
    {
        $connections = array_keys(config('database.connections', []));

        foreach ($connections as $conn) {
            try {
                tap(
                    app('db')->connection($conn),
                    function ($driver) {
                        if (self::driverSupportsTransactions($driver)) {
                            $driver->beginTransaction();
                        }
                    }
                );
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

        foreach ($connections as $conn) {
            try {
                tap(
                    app('db')->connection($conn),
                    function ($driver) {
                        if (self::driverSupportsTransactions($driver)) {
                            $driver->rollBack();
                        }
                    }
                );
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
        $methods = [ 'beginTransaction', 'rollback' ];

        foreach ($methods as $method) {
            if (! method_exists($driver, $method)) {
                return false;
            }
        }

        return true;
    }
}
