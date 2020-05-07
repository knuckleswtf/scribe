<?php

namespace Knuckles\Scribe\Extracting;

use Exception;

trait DatabaseTransactionHelpers
{
    /**
     * @param string $connection
     *
     * @return void
     */
    private function startDbTransaction(String $connection = null)
    {
        try {
            app('db')->connection($connection)->beginTransaction();
        } catch (Exception $e) {
        }
    }

    /**
     * @param string $connection
     *
     * @return void
     */
    private function endDbTransaction(String $connection = null)
    {
        try {
            app('db')->connection($connection)->rollBack();
        } catch (Exception $e) {
        }
    }
}
