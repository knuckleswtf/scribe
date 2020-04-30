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
        try {
            app('db')->beginTransaction();
        } catch (Exception $e) {
        }
    }

    /**
     * @return void
     */
    private function endDbTransaction()
    {
        try {
            app('db')->rollBack();
        } catch (Exception $e) {
        }
    }
}
