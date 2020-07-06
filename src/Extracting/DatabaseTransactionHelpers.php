<?php

namespace Knuckles\Scribe\Extracting;

use Exception;
use Knuckles\Scribe\Exceptions\DbTransactionSupportException;
use Knuckles\Scribe\Exceptions\ScribeException;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;

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
                $driver = app('db')->connection($conn);

                if (self::driverSupportsTransactions($driver)) {
                    $driver->beginTransaction();

                    return;
                }

                if ($this->isNoTransactionSupportAllowed($conn)) {
                    throw DbTransactionSupportException::create($conn, get_class($driver));
                }

                c::warn("Database driver for the connection [{$conn}] does not support transactions!");
                c::warn("Any changes made to your database will persist!");
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

        foreach ($connections as $conn) {
            try {
                $driver = app('db')->connection($conn);

                if (self::driverSupportsTransactions($driver)) {
                    $driver->rollBack();

                    return;
                }

                c::warn("Database driver for the connection [{$conn}] does not support transactions!");
                c::warn("Any changes made to your database will persist!");
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

    /**
     * Assesses whether drivers without transaction support can proceed
     *
     * @param string $connection_name Name of the connection
     *
     * @return boolean
     */
    private function isNoTransactionSupportAllowed(string $connection_name)
    {
        $config = $this->getConfig();

        $allow_list = $config->get('run_without_database_transactions', false);

        if (is_array($allow_list)) {
            return in_array($connection_name, $allow_list);
        }

        return false;
    }

    /**
     * Returns an instance of the documentation config
     *
     * @return DocumentationConfig
     */
    abstract public function getConfig();
}
