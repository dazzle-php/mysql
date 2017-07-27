<?php

namespace Dazzle\MySQL;

use Dazzle\Event\EventEmitterInterface;
use Dazzle\Loop\LoopResourceInterface;
use Dazzle\Promise\PromiseInterface;

interface DatabaseInterface extends SQLInterface, LoopResourceInterface, EventEmitterInterface
{
    /**
     * Start the MySQL connection.
     *
     * @return PromiseInterface
     */
    public function start();

    /**
     * Stop the MySQL connection.
     *
     * @return PromiseInterface
     */
    public function stop();

    /**
     * Create and being an new transaction.
     *
     * @return TransactionInterface
     */
    public function beginTransaction();

    /**
     * End all (rollback) currently opened transactions.
     */
    public function endTransaction(TransactionInterface $trans);

    /**
     * Check whether database has any pending transactions.
     *
     * @return bool
     */
    public function inTransaction();
}
