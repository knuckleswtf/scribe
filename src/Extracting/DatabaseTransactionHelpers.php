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
                app('db')->connection($conn)->beginTransaction();
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
                app('db')->connection($conn)->rollBack();
            } catch (Exception $e) {
            }
        }
    }
}
